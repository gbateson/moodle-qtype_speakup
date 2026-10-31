import terser from '@rollup/plugin-terser';

// Moodle modules, and this plugin's own modules (loaded separately by
// Moodle's RequireJS, so not bundled into each other).
const external = [
    'core/log', 'core/modal_factory', 'core/notification', 'core/templates',
    'qtype_speakup/avrecording', 'qtype_speakup/draftfiles',
];

export default [
    {
        input: 'amd/src/avrecording.js',
        external,
        output: {
            file: 'amd/build/avrecording.min.js',
            format: 'amd',
            amd: {id: 'qtype_speakup/avrecording'},
            sourcemap: true,
            exports: 'named',
            plugins: [terser()],
        },
    },
    {
        input: 'amd/src/draftfiles.js',
        external,
        output: {
            file: 'amd/build/draftfiles.min.js',
            format: 'amd',
            amd: {id: 'qtype_speakup/draftfiles'},
            sourcemap: true,
            exports: 'named',
            plugins: [terser()],
        },
    },
    {
        input: 'amd/src/audioprompt.js',
        external,
        output: {
            file: 'amd/build/audioprompt.min.js',
            format: 'amd',
            amd: {id: 'qtype_speakup/audioprompt'},
            sourcemap: true,
            exports: 'named',
            plugins: [terser()],
        },
    },
    {
        input: 'amd/src/prompttranscript.js',
        external,
        output: {
            file: 'amd/build/prompttranscript.min.js',
            format: 'amd',
            amd: {id: 'qtype_speakup/prompttranscript'},
            sourcemap: true,
            exports: 'named',
            plugins: [terser()],
        },
    },
    {
        input: 'amd/src/promptcontrol.js',
        external,
        output: {
            file: 'amd/build/promptcontrol.min.js',
            format: 'amd',
            amd: {id: 'qtype_speakup/promptcontrol'},
            sourcemap: true,
            exports: 'named',
            plugins: [terser()],
        },
    },
    {
        input: 'amd/src/fileupload.js',
        external,
        output: {
            file: 'amd/build/fileupload.min.js',
            format: 'amd',
            amd: {id: 'qtype_speakup/fileupload'},
            sourcemap: true,
            exports: 'named',
            plugins: [terser()],
        },
    },
    {
        input: 'amd/src/speechrecognition.js',
        external,
        output: {
            file: 'amd/build/speechrecognition.min.js',
            format: 'amd',
            amd: {id: 'qtype_speakup/speechrecognition'},
            sourcemap: true,
            exports: 'named',
            plugins: [terser()],
        },
    },
];
