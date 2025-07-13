const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'editor': './src/js/WordsurfPlugin.js',
        'admin': './src/js/admin/index.js',
        'diff-block': './includes/blocks/diff/src/index.js',
    },
    output: {
        path: __dirname + '/assets/js',
        filename: '[name].js',
    },
}; 