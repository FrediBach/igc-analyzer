/*jshint esversion: 6 */

const IGCAnalyzer = require('./dist/igc-analyzer');
const fs = require('fs');

const igcData = fs.readFileSync('test2.igc', 'utf8');
const analyzer = new IGCAnalyzer(igcData);
const analyzedData = analyzer.parse(true, true);

console.log('Track distance:', analyzedData.track.distance);
console.log('Max altitude:', analyzedData.track.maxaltitude);
console.log('Min altitude:', analyzedData.track.minaltitude);
console.log('Max altitude gain:', analyzedData.track.maxaltitudegain);
console.log('Max climb:', analyzedData.track.maxclimb);
console.log('Max sink:', analyzedData.track.maxsink);
console.log('Max speed:', analyzedData.track.maxspeed);
console.log('Duration:', analyzedData.track.duration);
console.log('Gliding duration:', analyzedData.track['gliding-duration']);
console.log('Thermaling duration:', analyzedData.track['thermaling-duration']);
console.log('Left thermaling duration:', analyzedData.track['left-thermaling-duration']);
console.log('Right thermaling duration:', analyzedData.track['right-thermaling-duration']);
console.log('Thermal count:', analyzedData.thermals.length);

const json = JSON.stringify(analyzedData);

fs.writeFileSync('./test2.json', json);