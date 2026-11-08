<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_speakup;

/**
 * Makes large response files smaller, in the background (see
 * task\assess_response and task\reduce_response_files).
 *
 * - Audio: ffmpeg, as mono MP3, which is plenty for speech.
 * - Video: ffmpeg, as MP4 (H.264), at most 1280 pixels wide or tall (720p).
 * - Images: PHP's GD, as JPEG, at most 2000 pixels wide or tall.
 * - PDFs: Ghostscript (Moodle's "Path to ghostscript" setting), with images at 150 dpi.
 *
 * Files are only made smaller if they are bigger than the size set in the
 * plugin's settings, and are only replaced if the result is clearly
 * smaller. If the tools are missing, or PHP cannot run programs, files are
 * kept as they are.
 *
 * For questions that send only the sound of videos to the AI, this also
 * takes the sound out of a video, as MP3, for the AI only.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_reducer {

    /** The default size above which files are made smaller, in MB. */
    const DEFAULT_THRESHOLD = 5;

    /** A smaller file is only kept if it is at most this fraction of the original's size. */
    const MIN_SAVING = 0.8;

    /** The longest a program may run, in seconds. */
    const TIMEOUT = 900;

    /** The bit rate of audio, mono. */
    const AUDIO_BITRATE = '48k';

    /** The longest side of a video, in pixels (720p). */
    const VIDEO_MAX_SIDE = 1280;

    /** The longest side of an image, in pixels. */
    const IMAGE_MAX_SIDE = 2000;

    /** The quality of JPEG images, 0 to 100. */
    const IMAGE_QUALITY = 80;

    /** The most pixels an image may have to be made smaller here (larger ones would use too much memory). */
    const IMAGE_MAX_PIXELS = 60000000;

    /** Image types that GD can make smaller. (Not GIF, which may be animated.) */
    const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp'];

    /** The file area for copies of files made only for the AI, deleted afterwards. */
    const AI_FILEAREA = 'aitemp';

    /**
     * Are large response files made smaller?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('qtype_speakup', 'reducefiles');
    }

    /**
     * Are large response files replaced by the smaller ones? If not, the
     * smaller ones are only made for the AI.
     *
     * @return bool
     */
    public static function replaces_originals(): bool {
        return (bool) get_config('qtype_speakup', 'replaceoriginals');
    }

    /**
     * The size above which files are made smaller.
     *
     * @return int bytes.
     */
    public static function get_threshold(): int {
        $mb = get_config('qtype_speakup', 'reducethreshold');
        $mb = ($mb === false || $mb === '') ? self::DEFAULT_THRESHOLD : (float) $mb;
        return (int) round(max(0, $mb) * 1048576);
    }

    /**
     * Can PHP run programs?
     *
     * @return bool
     */
    public static function can_exec(): bool {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));
        return !in_array('exec', $disabled);
    }

    /**
     * Get the path to ffmpeg, if it can be run.
     *
     * @return string|null
     */
    public static function get_ffmpeg(): ?string {
        $path = (string) get_config('qtype_speakup', 'pathtoffmpeg');
        return self::usable($path);
    }

    /**
     * Get the path to Ghostscript, from Moodle's own setting, if it can be run.
     *
     * @return string|null
     */
    public static function get_ghostscript(): ?string {
        global $CFG;
        return self::usable((string) ($CFG->pathtogs ?? ''));
    }

    /**
     * Get a program's path, if it can be run.
     *
     * @param string $path
     * @return string|null
     */
    protected static function usable(string $path): ?string {
        $path = trim($path);
        if ($path === '' || !self::can_exec() || !is_file($path) || !is_executable($path)) {
            return null;
        }
        return $path;
    }

    /**
     * Describe what can be made smaller on this site, for the settings page.
     *
     * @return string HTML
     */
    public static function get_status_html(): string {
        $conditions = [
            'exec' => self::can_exec(),
            'ffmpeg' => self::get_ffmpeg() !== null,
            'ghostscript' => self::get_ghostscript() !== null,
            'gd' => function_exists('imagecreatefromstring'),
        ];
        $items = '';
        foreach ($conditions as $name => $met) {
            $icon = $met ? '✔' : '✘';
            $class = $met ? 'text-success' : 'text-danger';
            $items .= \html_writer::tag('li', \html_writer::span($icon, $class . ' me-1') .
                    get_string('reducecondition_' . $name, 'qtype_speakup'));
        }
        return \html_writer::tag('ul', $items, ['class' => 'list-unstyled']);
    }

    /**
     * Make a smaller copy of a file, in a temporary folder.
     *
     * @param \stored_file $file
     * @param bool $audioonly for a video, take out only the sound.
     * @return array|null [path, filename, mimetype] of the copy, or null if it
     *      could not be made, or would not be clearly smaller (except for the
     *      sound of a video, which is always returned if it can be made).
     */
    public static function reduce(\stored_file $file, bool $audioonly = false): ?array {
        $mimetype = (string) $file->get_mimetype();
        $isaudio = (strpos($mimetype, 'audio/') === 0);
        $isvideo = (strpos($mimetype, 'video/') === 0);
        $basename = pathinfo($file->get_filename(), PATHINFO_FILENAME);

        if ($isaudio || ($isvideo && $audioonly)) {
            $extension = 'mp3';
        } else if ($isvideo) {
            $extension = 'mp4';
        } else if (in_array($mimetype, self::IMAGE_TYPES)) {
            $extension = 'jpg';
        } else if ($mimetype === 'application/pdf') {
            $extension = 'pdf';
        } else {
            return null;
        }

        $dir = make_request_directory();
        $input = $dir . '/input.' . (pathinfo($file->get_filename(), PATHINFO_EXTENSION) ?: 'bin');
        $output = $dir . '/output.' . $extension;
        if (!$file->copy_content_to($input)) {
            return null;
        }

        if ($isaudio || ($isvideo && $audioonly)) {
            $done = self::run_ffmpeg(['-i', $input, '-vn', '-ac', '1', '-c:a', 'libmp3lame', '-b:a', self::AUDIO_BITRATE,
                    $output]);
        } else if ($isvideo) {
            // At most 1280 pixels on the longest side, for landscape or portrait videos,
            // with even sizes, as H.264 needs.
            $side = self::VIDEO_MAX_SIDE;
            $scale = "scale='if(gte(iw,ih),min($side,iw),-2)':'if(gte(iw,ih),-2,min($side,ih))'";
            $done = self::run_ffmpeg(['-i', $input, '-vf', $scale, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28',
                    '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '64k', '-ac', '1', '-movflags', '+faststart', $output]);
        } else if ($extension === 'jpg') {
            $done = self::reduce_image($input, $output, $mimetype);
        } else {
            $done = self::run_ghostscript($input, $output);
        }

        clearstatcache();
        if (!$done || !is_file($output) || filesize($output) <= 0) {
            return null;
        }
        if (!($isvideo && $audioonly) && filesize($output) > $file->get_filesize() * self::MIN_SAVING) {
            return null;
        }
        return [$output, $basename . '.' . $extension, mimeinfo('type', 'file.' . $extension)];
    }

    /**
     * Replace a response's large files by smaller ones, if this site does so.
     *
     * @param \question_attempt $qa
     * @param int $contextid the context of the question usage.
     * @return int how many files were replaced.
     */
    public static function reduce_response(\question_attempt $qa, int $contextid): int {
        if (!self::is_enabled() || !self::replaces_originals()) {
            return 0;
        }
        $step = $qa->get_last_step_with_qt_var('recording');
        if (!$step->get_id()) {
            return 0;
        }
        $fs = get_file_storage();
        $count = 0;
        $threshold = self::get_threshold();
        foreach ($fs->get_area_files($contextid, 'question', 'response_recording', $step->get_id(), 'filename', false) as $file) {
            if ($file->get_filesize() <= $threshold) {
                continue;
            }
            try {
                $result = self::reduce($file);
                if (!$result) {
                    mtrace('qtype_speakup: ' . $file->get_filename() . ' (' . display_size($file->get_filesize()) .
                            ') could not be made smaller, so it was kept as it is.');
                    continue;
                }
                [$path, $filename, $mimetype] = $result;
                $record = [
                    'contextid' => $contextid,
                    'component' => 'question',
                    'filearea' => 'response_recording',
                    'itemid' => $step->get_id(),
                    'filepath' => $file->get_filepath(),
                    'filename' => $filename,
                    'mimetype' => $mimetype,
                    'userid' => $file->get_userid(),
                    'timecreated' => $file->get_timecreated(),
                ];
                $before = $file->get_filesize();
                $oldname = $file->get_filename();
                $file->delete();
                // E.g. "recording.mp4" from "recording.webm", if both existed.
                if ($existing = $fs->get_file($contextid, 'question', 'response_recording', $step->get_id(),
                        $record['filepath'], $filename)) {
                    $existing->delete();
                }
                $fs->create_file_from_pathname($record, $path);
                mtrace('qtype_speakup: ' . $oldname . ' (' . display_size($before) . ') was replaced by ' . $filename .
                        ' (' . display_size(filesize($path)) . ').');
                $count++;
            } catch (\Throwable $e) {
                mtrace('qtype_speakup: ' . $file->get_filename() . ' could not be made smaller: ' . $e->getMessage());
            }
        }
        return $count;
    }

    /**
     * Get the files to send to the AI: the response's files, with smaller
     * copies of large ones (if the site makes them smaller but keeps the
     * originals), and only the sound of videos (if the question asks for that).
     *
     * The copies are kept in a file area of their own, until
     * delete_ai_files() is called.
     *
     * @param \stored_file[] $files the response's files.
     * @param int $contextid the context of the question usage.
     * @param int $itemid the question attempt's id.
     * @param bool $audioonly send only the sound of videos.
     * @return \stored_file[]
     */
    public static function get_ai_files(array $files, int $contextid, int $itemid, bool $audioonly): array {
        self::delete_ai_files($contextid, $itemid);
        $reduce = self::is_enabled() && !self::replaces_originals();
        $threshold = self::get_threshold();
        $aifiles = [];
        foreach ($files as $file) {
            $isvideo = (strpos((string) $file->get_mimetype(), 'video/') === 0);
            $result = null;
            try {
                if ($audioonly && $isvideo) {
                    $result = self::reduce($file, true);
                    if (!$result) {
                        mtrace('qtype_speakup: the sound could not be taken out of ' . $file->get_filename() .
                                ', so the whole video was sent.');
                    }
                } else if ($reduce && $file->get_filesize() > $threshold) {
                    $result = self::reduce($file);
                }
                if ($result) {
                    [$path, $filename, $mimetype] = $result;
                    $aifiles[] = get_file_storage()->create_file_from_pathname([
                        'contextid' => $contextid,
                        'component' => 'qtype_speakup',
                        'filearea' => self::AI_FILEAREA,
                        'itemid' => $itemid,
                        'filepath' => '/',
                        'filename' => $filename,
                        'mimetype' => $mimetype,
                    ], $path);
                    mtrace('qtype_speakup: ' . $filename . ' (' . display_size(filesize($path)) . ') was sent to the AI, ' .
                            'in place of ' . $file->get_filename() . ' (' . display_size($file->get_filesize()) . ').');
                    continue;
                }
            } catch (\Throwable $e) {
                mtrace('qtype_speakup: ' . $file->get_filename() . ' could not be made smaller: ' . $e->getMessage());
            }
            $aifiles[] = $file;
        }
        return $aifiles;
    }

    /**
     * Delete the copies of files made for the AI.
     *
     * @param int $contextid
     * @param int $itemid the question attempt's id.
     */
    public static function delete_ai_files(int $contextid, int $itemid): void {
        get_file_storage()->delete_area_files($contextid, 'qtype_speakup', self::AI_FILEAREA, $itemid);
    }

    /**
     * Run ffmpeg.
     *
     * @param array $arguments
     * @return bool whether it worked.
     */
    protected static function run_ffmpeg(array $arguments): bool {
        $ffmpeg = self::get_ffmpeg();
        if ($ffmpeg === null) {
            return false;
        }
        return self::run(array_merge([$ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y'], $arguments));
    }

    /**
     * Make a PDF smaller with Ghostscript, with its images at 150 dpi.
     *
     * @param string $input
     * @param string $output
     * @return bool whether it worked.
     */
    protected static function run_ghostscript(string $input, string $output): bool {
        $gs = self::get_ghostscript();
        if ($gs === null) {
            return false;
        }
        return self::run([$gs, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5', '-dPDFSETTINGS=/ebook',
                '-dNOPAUSE', '-dQUIET', '-dBATCH', '-dSAFER', '-sOutputFile=' . $output, $input]);
    }

    /**
     * Run a program, at low priority and with a time limit, if the server
     * has "nice" and "timeout".
     *
     * @param array $command the program and its arguments.
     * @return bool whether it worked.
     */
    protected static function run(array $command): bool {
        $prefix = [];
        if (is_executable('/usr/bin/timeout')) {
            $prefix = ['/usr/bin/timeout', (string) self::TIMEOUT];
        }
        if (is_executable('/usr/bin/nice')) {
            $prefix = array_merge($prefix, ['/usr/bin/nice', '-n', '10']);
        }
        $line = implode(' ', array_map('escapeshellarg', array_merge($prefix, $command))) . ' 2>&1';
        $output = [];
        $code = 0;
        exec($line, $output, $code);
        if ($code !== 0) {
            mtrace('qtype_speakup: ' . basename($command[0]) . ' failed (' . $code . '): ' .
                    \core_text::substr(implode(' ', array_slice($output, -3)), 0, 500));
            return false;
        }
        return true;
    }

    /**
     * Make an image smaller with GD, as a JPEG, the right way up.
     *
     * @param string $input
     * @param string $output
     * @param string $mimetype
     * @return bool whether it worked.
     */
    protected static function reduce_image(string $input, string $output, string $mimetype): bool {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $size = @getimagesize($input);
        if (!$size || $size[0] * $size[1] > self::IMAGE_MAX_PIXELS) {
            return false;
        }
        raise_memory_limit(MEMORY_HUGE);
        $image = @imagecreatefromstring(file_get_contents($input));
        if (!$image) {
            return false;
        }

        // Phones save photos sideways, and say how to turn them in the EXIF data,
        // which a JPEG made by GD does not have.
        if ($mimetype === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($input);
            $angle = [3 => 180, 6 => 270, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($angle && ($rotated = imagerotate($image, $angle, 0))) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::IMAGE_MAX_SIDE / max($width, $height));
        $newwidth = max(1, (int) round($width * $scale));
        $newheight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newwidth, $newheight);
        // A white background, for images with transparent parts.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        imagedestroy($image);
        $done = imagejpeg($canvas, $output, self::IMAGE_QUALITY);
        imagedestroy($canvas);
        return $done;
    }
}
