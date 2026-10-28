<?php

declare(strict_types=1);

namespace Fxri\File;

use InvalidArgumentException;
use RuntimeException;

/**
 * 视频信息探测。
 *
 * 执行外部探测命令并解析输出，承接 1.x 门面 `File::getMediaInfo()` 的视频分支；
 * 命令由调用方传入，库内不固化 ffmpeg 路径与参数。
 */
final class VideoInfo
{
    /**
     * 读取视频信息。
     *
     * `$command` 传数组时按「二进制 + 参数」直接执行，不经 shell；传字符串时按 `%s` 占位
     * 代入经 `escapeshellarg()` 处理的文件路径，模板由调用方保证可信。
     *
     * @param string $file 视频文件路径
     * @param array<array-key, string>|string $command 探测命令
     * @return array<string, mixed> 视频信息
     */
    public static function inspect(string $file, array|string $command): array
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException('视频文件不存在：' . $file);
        }

        $output = self::run($command, $file);
        $data = [];
        $hasDuration = false;
        $seconds = 0.0;
        $start = '';

        if (1 === preg_match('/Duration: (.*?), start: (.*?), bitrate: (\d*) kb\/s/', $output, $match)) {
            $duration = explode(':', $match[1]);
            $seconds = (int) $duration[0] * 3600 + (int) ($duration[1] ?? 0) * 60 + (float) ($duration[2] ?? 0);
            $start = $match[2];
            $hasDuration = true;
            $data['duration'] = $match[1];
            $data['seconds'] = $seconds;
            $data['start'] = $start;
            $data['bitrate'] = (int) $match[3];
        }

        if (1 === preg_match('/Video: (.*?), (.*?), (.*?)[,\s]/', $output, $match)) {
            $resolution = explode('x', $match[3]);
            $data['vcodec'] = $match[1];
            $data['vformat'] = $match[2];
            $data['resolution'] = $match[3];
            $data['width'] = (int) $resolution[0];
            $data['height'] = (int) ($resolution[1] ?? 0);
        }

        if (1 === preg_match('/Audio: (\w*), (\d*) Hz/', $output, $match)) {
            $data['acodec'] = $match[1];
            $data['asamplerate'] = (int) $match[2];
        }

        if ($hasDuration) {
            $data['play_time'] = $seconds + (float) $start;
        }

        $data['size'] = (int) filesize($file);

        return $data;
    }

    /**
     * 执行探测命令并返回输出。
     *
     * @param array<array-key, string>|string $command 探测命令
     * @param string $file 视频文件路径
     * @return string 命令输出
     */
    private static function run(array|string $command, string $file): string
    {
        $command = is_string($command) ? sprintf($command, escapeshellarg($file)) : array_values($command);

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('无法执行媒体探测命令');
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        $output = is_string($output) ? $output : '';
        $error = is_string($error) ? $error : '';
        if (0 !== $code && '' === $output) {
            throw new RuntimeException('媒体探测失败：' . ('' === $error ? '命令返回 ' . $code : $error));
        }

        return $output;
    }
}
