import terser from '@rollup/plugin-terser';

export default {
    input: 'amd/src/avrecording.js',
    external: ['core/log', 'core/modal_factory', 'core/templates'],
    output: {
        file: 'amd/build/avrecording.min.js',
        format: 'amd',
        amd: {id: 'qtype_speakup/avrecording'},
        sourcemap: true,
        exports: 'named',
        plugins: [terser()],
    },
};
