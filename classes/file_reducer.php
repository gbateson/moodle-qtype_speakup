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
 * task\assess_response and task\reduce_response_files). The files are
 * converted by the AI bridge's media converter (tool_aibridge), whose
 * settings say where ffmpeg is.
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
 * Videos that are not MP4 (e.g. WebM, from browsers other than Safari) can
 * also be converted to MP4 whatever their size, so that they play on every
 * device: iPhones and iPads cannot play WebM.
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

    /** The bit rate of audio, mono. */
    const AUDIO_BITRATE = '48k';

    /** The longest side of an image, in pixels. */
    const IMAGE_MAX_SIDE = 2000;

    /** The quality of JPEG images, 0 to 100. */
    const IMAGE_QUALITY = 80;

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
     * Are videos that are not MP4 converted to MP4, whatever their size?
     *
     * @return bool
     */
    public static function converts_videos(): bool {
        return (bool) get_config('qtype_speakup', 'convertvideos');
    }

    /**
     * Are any of a response's files replaced, i.e. made smaller or converted?
     *
     * @return bool
     */
    public static function replaces_files(): bool {
        return (self::is_enabled() && self::replaces_originals()) || self::converts_videos();
    }

    /**
     * Should a file be converted to MP4, whatever its size?
     *
     * @param \stored_file $file
     * @return bool
     */
    public static function needs_converting(\stored_file $file): bool {
        $mimetype = (string) $file->get_mimetype();
        return self::converts_videos() && strpos($mimetype, 'video/') === 0 && $mimetype !== 'video/mp4';
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
        return \tool_aibridge\media_converter::can_exec();
    }

    /**
     * Get the path to ffmpeg, from the AI bridge's settings, if it can be run.
     *
     * @return string|null
     */
    public static function get_ffmpeg(): ?string {
        return \tool_aibridge\media_converter::get_ffmpeg();
    }

    /**
     * Get the path to Ghostscript, from Moodle's own setting, if it can be run.
     *
     * @return string|null
     */
    public static function get_ghostscript(): ?string {
        return \tool_aibridge\media_converter::get_ghostscript();
    }

    /**
     * Describe what can be made smaller on this site, for the settings page.
     *
     * @return string HTML
     */
    public static function get_status_html(): string {
        $url = new \moodle_url('/admin/settings.php', ['section' => 'tool_aibridge_settings']);
        return \tool_aibridge\media_converter::get_status_html() .
            \html_writer::div(get_string('reducesettings', 'qtype_speakup', $url->out()), 'small');
    }

    /**
     * Make a smaller copy of a file, in a temporary folder.
     *
     * @param \stored_file $file
     * @param bool $audioonly for a video, take out only the sound.
     * @param float $minsaving keep the copy only if it is at most this
     *      fraction of the original's size, or 0 to keep it whatever its size.
     * @return array|null [path, filename, mimetype] of the copy, or null if it
     *      could not be made, or would not be clearly smaller (except for the
     *      sound of a video, which is always returned if it can be made).
     */
    public static function reduce(\stored_file $file, bool $audioonly = false, float $minsaving = self::MIN_SAVING): ?array {
        return \tool_aibridge\media_converter::convert_file($file, [
            'audioonly' => $audioonly,
            'audioformat' => 'mp3',
            'audiobitrate' => self::AUDIO_BITRATE,
            'videoformat' => 'mp4',
            'imageformat' => 'jpg',
            'imagemaxside' => self::IMAGE_MAX_SIDE,
            'imagequality' => self::IMAGE_QUALITY,
            'reencode' => true,
            'pdf' => true,
            'minsaving' => $minsaving,
        ]);
    }

    /**
     * Replace a response's large files by smaller ones, and its videos that
     * are not MP4 by MP4 ones, if this site does so.
     *
     * @param \question_attempt $qa
     * @param int $contextid the context of the question usage.
     * @return int how many files were replaced.
     */
    public static function reduce_response(\question_attempt $qa, int $contextid): int {
        if (!self::replaces_files()) {
            return 0;
        }
        $reduce = self::is_enabled() && self::replaces_originals();
        $step = $qa->get_last_step_with_qt_var('recording');
        if (!$step->get_id()) {
            return 0;
        }
        $fs = get_file_storage();
        $count = 0;
        $threshold = self::get_threshold();
        foreach ($fs->get_area_files($contextid, 'question', 'response_recording', $step->get_id(), 'filename', false) as $file) {
            // Videos to be converted are kept whatever their new size.
            $convert = self::needs_converting($file);
            if (!$convert && !($reduce && $file->get_filesize() > $threshold)) {
                continue;
            }
            try {
                $result = self::reduce($file, false, $convert ? 0 : self::MIN_SAVING);
                if (!$result) {
                    $why = \tool_aibridge\media_converter::get_last_error();
                    mtrace('qtype_speakup: ' . $file->get_filename() . ' (' . display_size($file->get_filesize()) .
                            ') could not be ' . ($convert ? 'converted' : 'made smaller') . ', so it was kept as it is.' .
                            ($why === '' ? '' : ' ' . $why));
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
                if (
                    $existing = $fs->get_file(
                        $contextid,
                        'question',
                        'response_recording',
                        $step->get_id(),
                        $record['filepath'],
                        $filename
                    )
                ) {
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
}
