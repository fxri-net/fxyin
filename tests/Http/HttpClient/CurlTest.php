<?php

declare(strict_types=1);

namespace Fxri\Tests\Http\HttpClient;

use Fxri\File\Filesystem;
use Fxri\Http\HttpClient\Curl;
use Fxri\Http\HttpClient\CurlOptions;
use Fxri\Param\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * URL 级 HTTP 测试：用本机临时服务验证请求、响应头与下载。
 */
final class CurlTest extends TestCase
{
    /** 临时目录 */
    private string $root;

    /** 服务进程 */
    private mixed $process = null;

    /** 服务端口 */
    private int $port = 0;

    /** @var array<int, resource> 进程管道 */
    private array $pipes = [];

    /**
     * 建立临时目录并启动本机服务。
     */
    protected function setUp(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('未安装 ext-curl');
        }

        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-curl-' . bin2hex(random_bytes(6));
        Filesystem::makeDirectory($this->root);
        Filesystem::write($this->root . DIRECTORY_SEPARATOR . 'index.php', <<<'PHP'
            <?php
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            header('Content-Type: application/json; charset=utf-8');
            if ('/download' === $path) {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'file-content';
                return;
            }
            if ('/missing' === $path) {
                http_response_code(404);
                echo 'not found';
                return;
            }
            echo json_encode([
                'method' => $_SERVER['REQUEST_METHOD'],
                'query' => $_GET,
                'body' => file_get_contents('php://input'),
                'trace' => $_SERVER['HTTP_X_TRACE'] ?? '',
            ], JSON_UNESCAPED_UNICODE);
            PHP);

        $this->start();
    }

    /**
     * 停止服务并清理临时目录。
     */
    protected function tearDown(): void
    {
        $this->stop();
        if (isset($this->root)) {
            Filesystem::deleteDirectory($this->root);
        }
    }

    /**
     * GET 数据拼进查询串，响应头可按小写名读取。
     */
    public function testGetMergesQuery(): void
    {
        $response = Curl::request($this->url('/json?x=1'), ['a' => '2']);

        self::assertSame(200, $response->status());
        self::assertTrue($response->isSuccess());
        self::assertStringContainsString('application/json', $response->header('content-type'));

        $data = Json::decode($response->body());
        self::assertIsArray($data);
        self::assertSame('GET', $data['method']);
        self::assertSame(['x' => '1', 'a' => '2'], $data['query']);
    }

    /**
     * 非 GET 的数组数据作为请求体，按 RFC 3986 编码；映射式请求头可下发。
     */
    public function testPostBody(): void
    {
        $body = Curl::send($this->url('/json'), ['a' => '1', 'b' => ['c' => '2']], ['X-Trace' => 'abc'], 'POST');

        $data = Json::decode($body);
        self::assertIsArray($data);
        self::assertSame('POST', $data['method']);
        self::assertSame('a=1&b%5Bc%5D=2', $data['body']);
        self::assertSame('abc', $data['trace']);
    }

    /**
     * 传输选项可通过值对象定制。
     */
    public function testOptions(): void
    {
        $options = CurlOptions::make(timeout: 5, verify: false, userAgent: 'fxri-test');

        self::assertSame(5, $options->timeout);
        self::assertFalse($options->verify);
        self::assertSame('fxri-test', $options->userAgent);

        $response = Curl::request($this->url('/json'), [], ['X-Trace' => 'opt'], 'GET', $options);
        self::assertSame(200, $response->status());
    }

    /**
     * 下载把正文写入目标文件。
     */
    public function testDownload(): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . 'saved.txt';

        self::assertSame($file, Curl::download($this->url('/download'), $file));
        self::assertSame('file-content', Filesystem::read($file));
    }

    /**
     * 非 2xx 响应不写文件并抛异常。
     */
    public function testDownloadFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('下载失败：HTTP 404');

        Curl::download($this->url('/missing'), $this->root . DIRECTORY_SEPARATOR . 'missing.txt');
    }

    /**
     * 拼接本机服务地址。
     *
     * @param string $path 路径与查询串
     * @return string 地址
     */
    private function url(string $path): string
    {
        return 'http://127.0.0.1:' . $this->port . $path;
    }

    /**
     * 启动本机服务并等待就绪。
     */
    private function start(): void
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (false === $socket) {
            self::markTestSkipped('无法分配本机端口：' . $errstr);
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        $this->port = (int) substr($name, strrpos($name, ':') + 1);

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, '-t', $this->root],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
        );
        if (!is_resource($process)) {
            self::markTestSkipped('无法启动本机服务');
        }

        $this->process = $process;
        $this->pipes = $pipes;

        for ($try = 0; $try < 50; ++$try) {
            $client = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);
            if (false !== $client) {
                fclose($client);

                return;
            }
            usleep(100_000);
        }

        self::markTestSkipped('本机服务未能就绪');
    }

    /**
     * 停止本机服务。
     */
    private function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($this->process);
        }

        $this->process = null;
        $this->pipes = [];
    }
}
