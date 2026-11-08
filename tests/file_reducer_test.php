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

use question_bank;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');
require_once($CFG->dirroot . '/question/type/speakup/vendor/getID3/getid3/getid3.php');

/**
 * Tests for making large response files smaller. The audio and video tests
 * need ffmpeg, at /usr/bin/ffmpeg, and are skipped without it; the PDF test
 * needs Ghostscript.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\file_reducer
 * @covers    \qtype_speakup\task\reduce_response_files
 */
final class file_reducer_test extends \advanced_testcase {

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('reducefiles', 1, 'qtype_speakup');
        set_config('replaceoriginals', 1, 'qtype_speakup');
        set_config('reducethreshold', 1, 'qtype_speakup');
        set_config('pathtoffmpeg', '/usr/bin/ffmpeg', 'qtype_speakup');
    }

    /**
     * Skip a test if ffmpeg is missing.
     */
    protected function require_ffmpeg(): void {
        if (file_reducer::get_ffmpeg() === null) {
            $this->markTestSkipped('ffmpeg is not installed, or PHP cannot run it.');
        }
    }

    /**
     * Save a file in the file storage.
     *
     * @param string $filename
     * @param string $content
     * @return \stored_file
     */
    protected function make_file(string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
                'component' => 'qtype_speakup', 'filearea' => 'test', 'itemid' => 0, 'filepath' => '/',
                'filename' => $filename], $content);
    }

    /**
     * Make a photo-like image: a gradient with some noise.
     *
     * @param int $width
     * @param int $height
     * @param string $type "png" or "jpeg".
     * @return string the image's content.
     */
    protected function make_photo(int $width, int $height, string $type = 'png'): string {
        $image = imagecreatetruecolor($width, $height);
        mt_srand(1);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $noise = mt_rand(0, 40);
                imagesetpixel($image, $x, $y, ((int) (200 * $x / $width) + $noise) << 16 |
                        ((int) (200 * $y / $height) + $noise) << 8 | (100 + $noise));
            }
        }
        ob_start();
        ($type === 'png') ? imagepng($image) : imagejpeg($image, null, 100);
        imagedestroy($image);
        return ob_get_clean();
    }

    /**
     * Make an audio or video file with ffmpeg.
     *
     * @param string $filename e.g. "a.mp4".
     * @param array $arguments ffmpeg's arguments, before the output file.
     * @return \stored_file
     */
    protected function make_media(string $filename, array $arguments): \stored_file {
        $path = make_request_directory() . '/' . $filename;
        $command = array_merge(['/usr/bin/ffmpeg', '-nostdin', '-loglevel', 'error', '-y'], $arguments, [$path]);
        exec(implode(' ', array_map('escapeshellarg', $command)) . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
        return $this->make_file($filename, file_get_contents($path));
    }

    /**
     * Get the width and height of an image or video file.
     *
     * @param string $path
     * @return array [width, height]
     */
    protected function get_dimensions(string $path): array {
        $info = (new \getID3())->analyze($path);
        return [(int) ($info['video']['resolution_x'] ?? 0), (int) ($info['video']['resolution_y'] ?? 0)];
    }

    /**
     * Large images become JPEGs, at most 2000 pixels wide or tall.
     */
    public function test_reduce_image(): void {
        $file = $this->make_file('scan.png', $this->make_photo(3000, 1500));
        $result = file_reducer::reduce($file);

        $this->assertNotNull($result);
        [$path, $filename, $mimetype] = $result;
        $this->assertSame('scan.jpg', $filename);
        $this->assertSame('image/jpeg', $mimetype);
        $this->assertSame([2000, 1000], array_slice(getimagesize($path), 0, 2));
        $this->assertLessThan($file->get_filesize() * file_reducer::MIN_SAVING, filesize($path));
    }

    /**
     * A file that would not be clearly smaller is not changed.
     */
    public function test_reduce_not_smaller(): void {
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagejpeg($image, null, 50);
        imagedestroy($image);
        $this->assertNull(file_reducer::reduce($this->make_file('small.jpg', ob_get_clean())));
        $this->assertNull(file_reducer::reduce($this->make_file('notes.txt', 'Not media.')));
    }

    /**
     * Audio becomes mono MP3.
     */
    public function test_reduce_audio(): void {
        $this->require_ffmpeg();
        $file = $this->make_media('speech.wav', ['-f', 'lavfi', '-i', 'sine=frequency=300:duration=20', '-ac', '2']);
        [$path, $filename, $mimetype] = file_reducer::reduce($file);

        $this->assertSame('speech.mp3', $filename);
        $this->assertSame('audio/mp3', $mimetype);
        $this->assertLessThan($file->get_filesize() / 5, filesize($path));
        $info = (new \getID3())->analyze($path);
        $this->assertSame(1, (int) $info['audio']['channels']);
        $this->assertEqualsWithDelta(20, $info['playtime_seconds'], 0.5);
    }

    /**
     * Video becomes MP4, at most 1280 pixels wide (or tall, for portrait
     * videos); and its sound can be taken out, for the AI.
     */
    public function test_reduce_video(): void {
        $this->require_ffmpeg();
        $file = $this->make_media('talk.mp4', ['-f', 'lavfi', '-i', 'testsrc=size=1080x1920:rate=25:duration=3',
                '-f', 'lavfi', '-i', 'sine=frequency=300:duration=3', '-c:v', 'mpeg4', '-q:v', '1', '-c:a', 'pcm_s16le',
                '-f', 'mov']);
        [$path, $filename, $mimetype] = file_reducer::reduce($file);
        $this->assertSame('talk.mp4', $filename);
        $this->assertSame('video/mp4', $mimetype);
        $this->assertSame([720, 1280], $this->get_dimensions($path));
        $this->assertLessThan($file->get_filesize() * file_reducer::MIN_SAVING, filesize($path));

        [$path, $filename, $mimetype] = file_reducer::reduce($file, true);
        $this->assertSame('talk.mp3', $filename);
        $this->assertSame('audio/mp3', $mimetype);
        $this->assertSame([0, 0], $this->get_dimensions($path));
    }

    /**
     * Without ffmpeg, audio and video are kept as they are.
     */
    public function test_reduce_without_ffmpeg(): void {
        set_config('pathtoffmpeg', '/no/such/ffmpeg', 'qtype_speakup');
        $this->assertNull(file_reducer::get_ffmpeg());
        $wav = tts::pcm_to_wav(str_repeat("\x10\x20", 400000), 16000);
        $this->assertNull(file_reducer::reduce($this->make_file('speech.wav', $wav)));
    }

    /**
     * PDFs of scans have their images made smaller, with Ghostscript.
     */
    public function test_reduce_pdf(): void {
        if (file_reducer::get_ghostscript() === null) {
            $this->markTestSkipped('Ghostscript is not installed, or PHP cannot run it.');
        }
        // A 10 x 5 inch page with a 3000 x 1500 pixel (300 dpi) scan.
        $jpeg = $this->make_photo(3000, 1500, 'jpeg');
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 720 360] /Resources << /XObject << /Im0 4 0 R >> >> ' .
                    '/Contents 5 0 R >>',
            "<< /Type /XObject /Subtype /Image /Width 3000 /Height 1500 /ColorSpace /DeviceRGB /BitsPerComponent 8 " .
                    "/Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream",
            "<< /Length 32 >>\nstream\nq 720 0 0 360 0 0 cm /Im0 Do Q\n\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
        $file = $this->make_file('scan.pdf', $pdf);

        [$path, $filename, $mimetype] = file_reducer::reduce($file);
        $this->assertSame('scan.pdf', $filename);
        $this->assertSame('application/pdf', $mimetype);
        $this->assertLessThan($file->get_filesize() / 2, filesize($path));
        $this->assertStringStartsWith('%PDF', file_get_contents($path));
    }

    /**
     * Start an image response with a large photo, as the current user, and finish it.
     *
     * @return array [usage id, slot]
     */
    protected function attempt_with_photo(): array {
        global $USER;
        $this->setUser($this->getDataGenerator()->create_user());
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $questionid = $generator->create_question('speakup', 'image', ['category' => $category->id,
                'gradingmethod' => SPEAKUP_GRADING_MANUAL])->id;

        $quba = question_engine::make_questions_usage_by_activity('core_question_preview', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(question_bank::load_question($questionid), 1);
        $quba->start_all_questions();
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
                'filename' => '01-photo.png'], $this->make_photo(2400, 1800));
        $quba->process_action($slot, ['recording' => new \question_file_saver($draftid, 'question', 'response_recording')]);
        $quba->finish_all_questions();
        question_engine::save_questions_usage_by_activity($quba);
        return [$quba->get_id(), $slot];
    }

    /**
     * Get the names of a response's files.
     *
     * @param int $usageid
     * @param int $slot
     * @return string[]
     */
    protected function response_filenames(int $usageid, int $slot): array {
        $qa = question_engine::load_questions_usage_by_activity($usageid)->get_question_attempt($slot);
        return array_values(array_map(fn($file) => $file->get_filename(),
                $qa->get_last_qt_files('recording', \context_system::instance()->id)));
    }

    /**
     * The task replaces a response's large files by smaller ones, keeping
     * their order, unless the site keeps the originals.
     */
    public function test_reduce_response_files_task(): void {
        [$usageid, $slot] = $this->attempt_with_photo();
        $this->assertSame(['01-photo.png'], $this->response_filenames($usageid, $slot));

        $task = new task\reduce_response_files();
        $task->set_custom_data(['usageid' => $usageid, 'slot' => $slot, 'contextid' => \context_system::instance()->id]);

        set_config('replaceoriginals', 0, 'qtype_speakup');
        $this->expectOutputRegex('/1 file\(s\) were made smaller/');
        $task->execute();
        $this->assertSame(['01-photo.png'], $this->response_filenames($usageid, $slot));

        set_config('replaceoriginals', 1, 'qtype_speakup');
        $task->execute();
        $this->assertSame(['01-photo.jpg'], $this->response_filenames($usageid, $slot));
    }

    /**
     * If the site keeps the originals, smaller copies are made for the AI,
     * and deleted afterwards. Small files are sent as they are.
     */
    public function test_get_ai_files(): void {
        set_config('replaceoriginals', 0, 'qtype_speakup');
        $context = \context_system::instance();
        $large = $this->make_file('scan.png', $this->make_photo(2400, 1800));
        $small = $this->make_file('small.png', $this->make_photo(100, 100));

        ob_start();
        $files = file_reducer::get_ai_files([$large, $small], $context->id, 42, false);
        ob_end_clean();
        $this->assertCount(2, $files);
        $this->assertSame('scan.jpg', $files[0]->get_filename());
        $this->assertSame(file_reducer::AI_FILEAREA, $files[0]->get_filearea());
        $this->assertSame($small, $files[1]);

        file_reducer::delete_ai_files($context->id, 42);
        $this->assertTrue(get_file_storage()->is_area_empty($context->id, 'qtype_speakup', file_reducer::AI_FILEAREA, 42));
    }

    /**
     * For questions that send only the sound of videos, the AI gets the
     * sound, even of a small video, and even if the site keeps the originals.
     */
    public function test_get_ai_files_audio_only(): void {
        $this->require_ffmpeg();
        set_config('reducefiles', 0, 'qtype_speakup');
        $video = $this->make_media('talk.webm', ['-f', 'lavfi', '-i', 'testsrc=size=320x240:rate=10:duration=2',
                '-f', 'lavfi', '-i', 'sine=frequency=300:duration=2', '-c:v', 'libvpx', '-c:a', 'libvorbis']);

        ob_start();
        $files = file_reducer::get_ai_files([$video], \context_system::instance()->id, 7, true);
        ob_end_clean();
        $this->assertSame('talk.mp3', $files[0]->get_filename());
        $this->assertSame('audio/mp3', $files[0]->get_mimetype());

        ob_start();
        $files = file_reducer::get_ai_files([$video], \context_system::instance()->id, 7, false);
        ob_end_clean();
        $this->assertSame($video, $files[0]);
    }
}
