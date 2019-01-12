# IGC Analyzer

This is a port of an IGC parser/analyzer that I've wrote in PHP a few years ago. The conversion is a little buggy right now, so don't use it for anything serious.

## Usage

Installation:

`npm i igc-analyzer --save`

And than in your script:

```
const IGCAnalyzer = require('./dist/igc-analyzer');
const fs = require('fs');

const igcData = fs.readFileSync('test2.igc', 'utf8');

const analyzer = new IGCAnalyzer(igcData);
const analyzedData = analyzer.parse(true, true);
```