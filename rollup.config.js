module.exports = {
    input: 'src/igc-analyzer.js',
    output: [
        {
            file: 'dist/igc-analyzer.js',
            format: 'cjs',
            name: 'IGCAnalyzer'
        },
        {
            file: 'dist/igc-analyzer.umd.js',
            format: 'umd',
            name: 'IGCAnalyzer'
        },
        {
            file: 'dist/igc-analyzer.amd.js',
            format: 'amd',
            name: 'IGCAnalyzer'
        },
        {
            file: 'dist/igc-analyzer.esm.js',
            format: 'esm',
            name: 'IGCAnalyzer'
        },
        {
            file: 'dist/igc-analyzer.iife.js',
            format: 'iife',
            name: 'IGCAnalyzer'
        }
    ]
};