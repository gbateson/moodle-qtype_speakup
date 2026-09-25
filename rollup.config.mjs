import terser from '@rollup/plugin-terser';

const external = ['core/log', 'core/modal_factory', 'core/templates'];

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
