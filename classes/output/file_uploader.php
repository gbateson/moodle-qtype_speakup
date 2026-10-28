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

namespace qtype_speakup\output;

use renderable;
use renderer_base;
use templatable;

/**
 * The file upload widget, shown instead of the recorder when a question's
 * response method is "Upload". The student chooses an audio or video file,
 * which is uploaded to the same draft file area that a recording would be
 * (see amd/src/fileupload.js).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_uploader implements renderable, templatable {

    /** @var string|null URL of the file already uploaded, if any. */
    protected $fileurl;

    /** @var string the MIME type of the file already uploaded, if any. */
    protected $mimetype;

    /** @var string the file types the file picker offers, e.g. ".mp3,.m4a,...". */
    protected $accept;

    /** @var bool whether the current user should see options to download the file. */
    protected $candownload;

    /**
     * Constructor.
     *
     * @param string|null $fileurl URL of the file already uploaded, if any.
     * @param string $mimetype the MIME type of that file.
     * @param string $accept the file types the file picker offers.
     * @param bool $candownload whether the current user should see options to download the file.
     */
    public function __construct(?string $fileurl, string $mimetype, string $accept, bool $candownload) {
        $this->fileurl = $fileurl;
        $this->mimetype = $mimetype;
        $this->accept = $accept;
        $this->candownload = $candownload;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $hasfile = !empty($this->fileurl);
        return [
            'hasfile' => $hasfile,
            'fileurl' => $hasfile ? $this->fileurl : '',
            'isvideo' => $hasfile && strpos($this->mimetype, 'video/') === 0,
            'accept' => $this->accept,
            'candownload' => $this->candownload,
        ];
    }
}
