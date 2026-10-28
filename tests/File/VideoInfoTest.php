<?php

declare(strict_types=1);

namespace Fxri\Tests\File;

use Fxri\File\Filesystem;
use Fxri\File\VideoInfo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 视频信息测试：用 PHP 自身输出固定文本模拟探测命令。
 */
final class VideoInfoTest extends TestCase
{
    /** 临时视频文件 */
    private string $file;

    /**
     * 建立临时文件。
     */
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-video-' . bin2hex(random_bytes(6)) . '.mp4';
        Filesystem::write($this->file, 'video');
    }

    /**
     * 清理临时文件。
     */
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * 解析时长、视频、音频与文件大小。
     */
    public function testInspect(): void
    {
        $info = VideoInfo::inspect($this->file, [PHP_BINARY, '-r', $this->probeCode()]);

        self::assertSame('00:02:05.50', $info['duration']);
        self::assertSame(125.5, $info['seconds']);
        self::assertSame('1.25', $info['start']);
        self::assertSame(500, $info['bitrate']);
        self::assertSame(126.75, $info['play_time']);
        self::assertSame('h264', $info['vcodec']);
        self::assertSame('yuv420p', $info['vformat']);
        self::assertSame('1280x720', $info['resolution']);
        self::assertSame(1280, $info['width']);
        self::assertSame(720, $info['height']);
        self::assertSame('aac', $info['acodec']);
        self::assertSame(44100, $info['asamplerate']);
        self::assertSame(5, $info['size']);
    }

    /**
     * 文件不存在时立即失败。
     */
    public function testMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('视频文件不存在');

        VideoInfo::inspect($this->file . '.missing', [PHP_BINARY, '-r', '']);
    }

    /**
     * 命令失败且无输出时抛异常。
     */
    public function testCommandFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        VideoInfo::inspect($this->file, [PHP_BINARY, '-r', 'fwrite(STDERR, "boom"); exit(1);']);
    }

    /**
     * 模拟 ffmpeg 输出的探测代码。
     *
     * @return string PHP 代码
     */
    private function probeCode(): string
    {
        return 'echo "Duration: 00:02:05.50, start: 1.25, bitrate: 500 kb/s\n'
            . 'Video: h264, yuv420p, 1280x720, 25 fps\n'
            . 'Audio: aac, 44100 Hz, stereo";';
    }
}
