'use strict';

function _interopDefault(ex) {
  return (ex && (typeof ex === 'object') && 'default' in ex) ? ex['default'] : ex;
}

var _ = _interopDefault(require('lodash'));
var ss = _interopDefault(require('simple-statistics'));

/*jshint esversion: 6 */

class IGCAnalyzer {

  constructor(igcData) {

    this.config = {
      'min-thermal-duration': 20,
      'min-upwind-duration': 20,
      'min-sinkarea-vario': -1.5,
      'min-sinkarea-duration': 20,
      'max-thermal-separation': 20,
      'max-upwind-separation': 20,
      'max-sinkarea-separation': 20,
      'max-vario': 25,
      'min-vario': -25,
      'max-speed': 150,
      'progress-glide-angle': 8,
      'min-turning-detection-duration': 15,
      'min-turning-bearing-difference': 45,
      'min-turning-bearing-deviation': 30,
      'wind-speed-correction-factor': 2
    };

    this.lines = []; // all lines in the igc file
    this.fixes = []; // all gps fixes
    this.metadata = {}; // all the metadata found
    this.fixadditions = []; // all additional data to be found in a fix

    this.lastfix = []; // Last fix used to calculate new fix values
    this.minStepDuration = 1; // In seconds, duration between saved fixes

    this.thermals = []; // All major thermals, with metadata
    this.currentThermal = {}; // Data of the current thermal

    this.upwinds = []; // All major upwind (soaring) areas
    this.currentUpwind = {}; // Current upwind area

    this.sinks = []; // All major sink areas, with metadata
    this.currentSink = {}; // Data of the current sink area

    this.windspeeds = []; // All windspeeds (taken from thermals)

    this.bases = []; // Base heights (taken from thermals)

    this.nochange = true; // Tracks changes between fixes

    this.track = { // Overall track statistics
      'distance': 0,
      'maxaltitude': -1000000,
      'minaltitude': 1000000,
      'maxaltitudegain': 0,
      'maxclimb': 0,
      'maxsink': 0,
      'maxspeed': 0,
      'duration': 0,
      'gliding-duration': 0,
      'thermaling-duration': 0,
      'left-thermaling-duration': 0,
      'right-thermaling-duration': 0,
      'thermals': [],
      'upwinds': [],
      'sinks': [],
      'windspeeds': [],
      'bases': [],
      'general': []
    };

    this.route = { // The route takeoff, landing and turnpoints
      'takeoff': [],
      'landing': [],
      'route': []
    };

    this.codes = { // IGC three letter codes
      'ACX': 'Linear accelerations X axis',
      'ACY': 'Linear accelerations Y axis',
      'ACZ': 'Linear accelerations Z axis',
      'ANX': 'Angular accelerations X axis',
      'ANY': 'Angular accelerations Y axis',
      'ANZ': 'Angular accelerations Z axis',
      'ATS': 'Altimeter pressure setting',
      'BFI': 'Blind Flying Instrument',
      'CCL': 'Competition class',
      'CCN': 'Camera Connect',
      'CCO': 'Compass course',
      'CDC': 'Camera Disconnect',
      'CGD': 'Change of geodetic datum',
      'CID': 'Competition ID',
      'CLB': 'Club or organisation, and country, from which flown or operated',
      'CM2': 'Second Crew Member\'s Name',
      'DAE': 'Displacement east',
      'DAN': 'Displacement north',
      'DB1': 'Date of Birth of the pilot-in-charge',
      'DB2': 'Date of Birth of second crew member',
      'DOB': 'Date of Birth of the pilot',
      'DTE': 'Date',
      'DTM': 'Geodetic Datum',
      'EDN': 'Engine down',
      'ENL': 'Environmental Noise Level',
      'EOF': 'Engine off',
      'EON': 'Engine on',
      'EUP': 'Engine up',
      'FIN': 'Finish',
      'FLP': 'Flap position',
      'FRS': 'Flight Recorder Security',
      'FTY': 'Flight Recorder Type',
      'FXA': 'Fix accuracy',
      'GAL': 'Galileo System',
      'GCN': 'GNSS Connect',
      'GDC': 'GNSS Disconnect',
      'GID': 'Glider ID',
      'GLO': 'GLONASS System',
      'GPS': 'GPS System',
      'GSP': 'Groundspeed',
      'GTY': 'Glider type',
      'HDM': 'Heading Magnetic',
      'HDT': 'Heading True',
      'IAS': 'Airspeed',
      'LAD': 'The last places of decimal minutes of latitude',
      'LOD': 'The last places of decimal minutes of longitude',
      'LOV': 'Low voltage',
      'MAC': 'MacCready setting',
      'MOP': 'Means of Propulsion',
      'OA1': 'Position of other aircraft',
      'OA2': 'Position of other aircraft',
      'OA3': 'Position of other aircraft',
      'OA4': 'Position of other aircraft',
      'OA5': 'Position of other aircraft',
      'OAT': 'Outside air temperature',
      'ONT': 'On Task',
      'OOI': 'OO ID',
      'PEV': 'Pilot EVent',
      'PFC': 'Post-Flight Claim',
      'PHO': 'Photo taken',
      'PLT': 'Pilot-in-charge',
      'PRS': 'Pressure Altitude Sensor',
      'RAI': 'RAIM',
      'REX': 'Record addition',
      'RFW': 'Firmware Revision Version of FR',
      'RHW': 'Hardware Revision Version of FR',
      'SCM': 'Second Crew Member\'s Name',
      'SEC': 'Security',
      'SIT': 'Site',
      'SIU': 'Satellites in use',
      'STA': 'Start event',
      'TAS': 'Airspeed True',
      'TDS': 'Decimal seconds of UTC time',
      'TEN': 'Total Energy Altitude',
      'TPC': 'Turn point confirmation',
      'TRM': 'Track Magnetic',
      'TRT': 'Track True',
      'TZN': 'Time Zone Offset',
      'UND': 'Undercarriage',
      'UNT': 'Units of Measure',
      'VAR': 'Uncompensated variometer',
      'VAT': 'Compensated variometer',
      'VXA': 'Vertical Fix Accuracy',
      'WDI': 'Wind Direction',
      'WSP': 'Wind speed'
    };

    this.lines = _.trim(igcData).split('\n');
  }

  // Set minimum fix duration:

  setMinStepDuration(value) {
    this.minStepDuration = Number(value);
  }

  // The main parse method:

  parse(extracalculations = false, filter = false) {

    for (let line of this.lines) {
      line = _.trim(line);

      const type = line.substr(0, 1).toUpperCase();
      const linedata = line.substr(1);

      switch (type) {
        case 'A':
          this.parseARecord(linedata);
          break;
        case 'H':
          this.parseHRecord(linedata);
          break;
        case 'I':
          this.parseIRecord(linedata);
          break;
        case 'B':
          this.parseBRecord(linedata, extracalculations, filter);
          break;
      }

    }

    if (filter) {

      // Remove unneaded entries after landing

      let last = [];

      for (let i = this.fixes.length - 1; i >= 0; i--) {

        let fix = this.fixes[i];

        if (last.length > 0) {
          if (fix.lat != last.lat || fix.lng != last.lng || fix.altitude != last.altitude) {

            this.fixes = this.fixes.slice(0, i + 2);

            this.route.landing = {
              'lat': this.fixes[i + 1].lat,
              'lng': this.fixes[i + 1].lng,
              'alt': this.fixes[i + 1].altitude,
              'h': this.fixes[i + 1].time.h,
              'm': this.fixes[i + 1].time.m,
              's': this.fixes[i + 1].time.s
            };

            this.track.duration = (this.route.landing.s + this.route.landing.m * 60 + this.route.landing.h * 60 * 60) - (this.route.takeoff.s + this.route.takeoff.m * 60 + this.route.takeoff.h * 60 * 60);

            break;

          }
        }

        last = fix;

      }

    }

    if (extracalculations) {

      // Because we need multiple samples, status is alway 10 seconds delayed, so fix that first:

      let steps = 2; // <- make this dynamic!

      for (let i = 0; i < this.fixes.length; i++) {
        if (typeof this.fixes[i - steps] !== 'undefined') {
          this.fixes[i - steps].status = this.fixes[i].status;
        }
      }

      // Now do the extra calculations:

      for (let f of this.fixes) {

        if (f.status === 'g') this.track['gliding-duration'] = this.track['gliding-duration'] + f.duration;
        if (f.status === 't') {
          this.track['thermaling-duration'] = this.track['thermaling-duration'] + f.duration;
          if (f['turn-dir'] === 'L') this.track['left-thermaling-duration'] = this.track['left-thermaling-duration'] + f.duration;
          if (f['turn-dir'] === 'R') this.track['right-thermaling-duration'] = this.track['right-thermaling-duration'] + f.duration;
        }

        // Thermal detection:

        if (f.status === 't') {

          // We're turning, maybe we're in a thermal. Add fix to the current thermal if it's one:

          if (typeof this.currentThermal.start === 'undefined') {
            this.currentThermal.start = f;
            this.currentThermal['min-climb'] = f.vario;
            this.currentThermal['max-climb'] = f.vario;
          } else {
            if (f.vario < this.currentThermal['min-climb']) {
              this.currentThermal['min-climb'] = f.vario;
            }
            if (f.vario > this.currentThermal['max-climb']) {
              this.currentThermal['max-climb'] = f.vario;
            }
          }

          this.currentThermal.end = f;

        } else {

          if (typeof this.currentThermal.start !== 'undefined') {

            // We're gliding, maybe we left a thermal? If yes, and if the current thermal is big enough, add it to the thermals array:

            this.currentThermal.duration = this.currentThermal.end.time.t - this.currentThermal.start.time.t;
            this.currentThermal.heightgain = this.currentThermal.end.altitude - this.currentThermal.start.altitude;

            if (this.currentThermal.duration > this.config['min-thermal-duration'] && this.currentThermal.heightgain > 0) {

              this.currentThermal.climbrate = this.currentThermal.heightgain / this.currentThermal.duration;
              this.currentThermal.distance = this.distance(this.currentThermal.start.lat, this.currentThermal.start.lng, this.currentThermal.end.lat, this.currentThermal.end.lng, "K") * 1000;
              this.currentThermal.bearing = this.bearing(this.currentThermal.start.lat, this.currentThermal.start.lng, this.currentThermal.end.lat, this.currentThermal.end.lng);

              if (this.currentThermal !== null) {

                this.thermals.push(this.currentThermal);
              }

            }

          }

          this.currentThermal = {};

        }

        // Upwind detection:

        if (f.status === 'g' && f.vario >= 0) {

          // We're gliding and not sinking, maybe we're in an upwind. Add fix to the current upwind if it's one:

          if (this.currentUpwind.length === 0) {
            this.currentUpwind.start = f;
            this.currentUpwind['max-climb'] = f.vario;
          } else {
            if (f.vario > this.currentUpwind['max-climb']) {
              this.currentUpwind['max-climb'] = f.vario;
            }
          }

          this.currentUpwind.end = f;

        } else {

          if (this.currentUpwind.length > 0) {

            // We're gliding, maybe we left a thermal? If yes, and if the current thermal is big enough, add it to the thermals array:

            this.currentUpwind.duration = this.currentUpwind.end.time.t - this.currentUpwind.start.time.t;
            this.currentUpwind.heightgain = this.currentUpwind.end.altitude - this.currentUpwind.start.altitude;

            if (this.currentUpwind.duration > this.config['min-upwind-duration'] && this.currentUpwind.heightgain > 0) {

              this.currentUpwind.climbrate = this.currentUpwind.heightgain / this.currentUpwind.duration;
              this.currentUpwind.distance = this.distance(this.currentUpwind.start.lat, this.currentUpwind.start.lng, this.currentUpwind.end.lat, this.currentUpwind.end.lng, "K") * 1000;
              this.currentUpwindbearing = this.bearing(this.currentUpwind.start.lat, this.currentUpwind.start.lng, this.currentUpwind.end.lat, this.currentUpwind.end.lng);
              if (this.currentUpwind !== null) {

                this.upwinds.push(this.currentUpwind);
              }


            }

          }

          this.currentUpwind = {};

        }

        // Sink area detection:

        if (f.status == 'g' && f.vario <= this.config['min-sinkarea-vario']) {

          // We're gliding in sink, maybe we're in a sink area. Add fix to the current sink if it's one:

          if (this.currentSink.length === 0) {
            this.currentSink.start = f;
            this.currentSink['max-sink'] = f.vario;
          } else {
            if (f.vario < this.currentSink['max-sink']) {
              this.currentSink['max-sink'] = f.vario;
            }
          }

          this.currentSink.end = f;

        } else {

          if (this.currentSink.length > 0) {

            // We're probably out of sink. If yes, and if the current sink area is big enough, add it to the sinks array:

            this.currentSink.duration = this.currentSink.end.time.t - this.currentSink.start.time.t;
            this.currentSink.heightlost = this.currentSink.start.altitude - this.currentSink.end.altitude;

            if (this.currentSink.duration > this.config['min-sinkarea-duration'] && this.currentSink.heightlost > 0) {

              this.currentSink.sinkrate = this.currentSink.heightlost / this.currentSink.duration * -1;
              this.currentSink.distance = this.distance(this.currentSink.start.lat, this.currentSink.start.lng, this.currentSink.end.lat, this.currentSink.end.lng, "K") * 1000;
              this.currentSink.bearing = this.bearing(this.currentSink.start.lat, this.currentSink.start.lng, this.currentSink.end.lat, this.currentSink.end.lng);

              if (this.currentSink !== null) {
                this.sinks.push(this.currentSink);
              }


            }

          }

          this.currentSink = {};

        }

      }

      // Combine thermals that are very close together:

      let last = [];

      last = 0;
      for (let i = 1; i < this.thermals.length; i++) {

        if (this.thermals[i].start.time.t - this.thermals[i - 1].end.time.t < this.config['max-thermal-separation']) {

          this.thermals[last].end = this.thermals[i].end;
          this.thermals[i].merged = true;

          this.thermals[last].duration = this.thermals[last].end.time.t - this.thermals[last].start.time.t;
          this.thermals[last].heightgain = this.thermals[last].end.altitude - this.thermals[last].start.altitude;
          this.thermals[last].climbrate = this.thermals[last].heightgain / this.thermals[last].duration;
          this.thermals[last].distance = this.distance(this.thermals[last].start.lat, this.thermals[last].start.lng, this.thermals[last].end.lat, this.thermals[last].end.lng, "K") * 1000;
          this.thermals[last].bearing = this.bearing(this.thermals[last].start.lat, this.thermals[last].start.lng, this.thermals[last].end.lat, this.thermals[last].end.lng);

        } else {
          last = i;
        }

      }

      for (let k in this.thermals) {
        let v = this.thermals[k];
        if (typeof v.merged !== 'undefined') {
          this.thermals.splice(k, 1);
        }
      }

      // Combine upwinds that are very close together:

      last = [];

      last = 0;
      for (let i = 1; i < this.upwinds.length; i++) {

        if (this.upwinds[i].start.time.t - this.upwinds[i - 1].end.time.t < this.config['max-upwind-separation']) {

          this.upwinds[last].end = this.upwinds[i].end;
          this.upwinds[i].merged = true;

          this.upwinds[last].duration = this.upwinds[last].end.time.t - this.upwinds[last].start.time.t;
          this.upwinds[last].heightgain = this.upwinds[last].end.altitude - this.upwinds[last].start.altitude;
          this.upwinds[last].climbrate = this.upwinds[last].heightgain / this.upwinds[last].duration;
          this.upwinds[last].distance = this.distance(this.upwinds[last].start.lat, this.upwinds[last].start.lng, this.upwinds[last].end.lat, this.upwinds[last].end.lng, "K") * 1000;
          this.upwinds[last].bearing = this.bearing(this.upwinds[last].start.lat, this.upwinds[last].start.lng, this.upwinds[last].end.lat, this.upwinds[last].end.lng);

        } else {
          last = i;
        }

      }

      for (let k in this.upwinds) {
        let v = this.upwinds[k];
        if (typeof v.merged !== 'undefined') {
          this.upwinds.splice(k, 1);
        }
      }

      // Combine sinks that are very close together:

      last = [];

      last = 0;
      for (let i = 1; i < this.sinks.length; i++) {

        if (this.sinks[i].start.time.t - this.sinks[i - 1].end.time.t < this.config['max-sinkarea-separation']) {

          this.sinks[last].end = this.sinks[i].end;
          this.sinks[i].merged = true;

          this.sinks[last].duration = this.sinks[last].end.time.t - this.sinks[last].start.time.t;
          this.sinks[last].heightlost = this.sinks[last].start.altitude - this.sinks[last].end.altitude;
          this.sinks[last].sinkrate = this.sinks[last].heightlost / this.sinks[last].duration * -1;
          this.sinks[last].distance = this.distance(this.sinks[last].start.lat, this.sinks[last].start.lng, this.sinks[last].end.lat, this.sinks[last].end.lng, "K") * 1000;
          this.sinks[last].bearing = this.bearing(this.sinks[last].start.lat, this.sinks[last].start.lng, this.sinks[last].end.lat, this.sinks[last].end.lng);

        } else {
          last = i;
        }

      }

      for (let k in this.sinks) {
        let v = this.sinks[k];
        if (typeof v.merged !== 'undefined') {
          this.sinks.splice(k, 1);
        }
      }

      // Calculate thermal stats:
      this.track.thermals = {};
      this.track.thermals.cnt = this.thermals.length;

      if (this.track.thermals.cnt > 0) {
        this.track.thermals['per-hour'] = this.track.thermals.cnt / (this.track.duration / 60 / 60);

        let climbs = [];
        let durations = [];
        let heightgains = [];
        let gaps = [];
        last = [];

        for (let t of this.thermals) {
          if (t === undefined) continue;
          climbs.push(t['max-climb']);
          durations.push(t.duration);
          heightgains.push(t.heightgain);
          if (last.length > 0) {
            gaps.push(t.start.time.t - last.end.time.t);
          }
          last = t;
        }
        if (climbs.length > 0) {
          this.track.thermals['min-climb'] = ss.min(climbs);
          this.track.thermals['max-climb'] = ss.max(climbs);
          this.track.thermals['mean-climb'] = ss.mean(climbs);
          this.track.thermals['median-climb'] = ss.median(climbs);
          this.track.thermals['climb-deviation'] = ss.standardDeviation(climbs);
          if (climbs.length > 1) {
            this.track.thermals['climb-variance'] = ss.sampleVariance(climbs);
          }

        }

        if (durations.length > 0) {
          this.track.thermals['sum-duration'] = ss.sum(durations);
          this.track.thermals['min-duration'] = ss.min(durations);
          this.track.thermals['max-duration'] = ss.max(durations);
          this.track.thermals['mean-duration'] = ss.mean(durations);
          this.track.thermals['median-duration'] = ss.median(durations);
          this.track.thermals['duration-deviation'] = ss.standardDeviation(durations);
          if (durations.length > 1) {
            this.track.thermals['duration-variance'] = ss.sampleVariance(durations);
          }

        }

        if (heightgains.length > 0) {
          this.track.thermals['sum-heightgain'] = ss.sum(heightgains);
          this.track.thermals['min-heightgain'] = ss.min(heightgains);
          this.track.thermals['max-heightgain'] = ss.max(heightgains);
          this.track.thermals['mean-heightgain'] = ss.mean(heightgains);
          this.track.thermals['median-heightgain'] = ss.median(heightgains);
          this.track.thermals['heightgain-deviation'] = ss.standardDeviation(heightgains);
          if (heightgains.length > 1) {
            this.track.thermals['heightgain-variance'] = ss.sampleVariance(heightgains);
          }

        }


        if (gaps.length > 0) {
          this.track.thermals['min-gap'] = ss.min(gaps);
          this.track.thermals['max-gap'] = ss.max(gaps);
          this.track.thermals['mean-gap'] = ss.mean(gaps);
          this.track.thermals['median-gap'] = ss.median(gaps);
          this.track.thermals['gap-deviation'] = ss.standardDeviation(gaps);
          if (gaps.length > 1) {
            this.track.thermals['gap-variance'] = ss.sampleVariance(gaps);
          }

        }
      }

      // Calculate upwind stats:
      this.track.upwinds = {};
      this.track.upwinds.cnt = this.upwinds.length;

      if (this.track.upwinds.cnt > 0) {
        this.track.upwinds['per-hour'] = this.track.upwinds.cnt / (this.track.duration / 60 / 60);

        let climbs = [];
        let durations = [];
        let heightgains = [];
        let gaps = [];
        last = [];
        for (let t of this.upwinds) {
          climbs.push(t['max-climb']);
          durations.push(t.duration);
          heightgains.push(t.heightgain);
          if (last.length > 0) {
            gaps.push(t.start.time.t - last.end.time.t);
          }
          last = t;
        }

        this.track.upwinds['min-climb'] = ss.min(climbs);
        this.track.upwinds['max-climb'] = ss.max(climbs);
        this.track.upwinds['mean-climb'] = ss.mean(climbs);
        this.track.upwinds['median-climb'] = ss.median(climbs);
        this.track.upwinds['climb-deviation'] = ss.standardDeviation(climbs);

        if (climbs.length > 1) {
          this.track.upwinds['climb-variance'] = ss.sampleVariance(climbs);
        }

        this.track.upwinds['sum-duration'] = ss.sum(durations);
        this.track.upwinds['min-duration'] = ss.min(durations);
        this.track.upwinds['max-duration'] = ss.max(durations);
        this.track.upwinds['mean-duration'] = ss.mean(durations);
        this.track.upwinds['median-duration'] = ss.median(durations);
        this.track.upwinds['duration-deviation'] = ss.standardDeviation(durations);
        if (durations.length > 1) {
          this.track.upwinds['duration-variance'] = ss.sampleVariance(durations);
        }


        this.track.upwinds['sum-heightgain'] = ss.sum(heightgains);
        this.track.upwinds['min-heightgain'] = ss.min(heightgains);
        this.track.upwinds['max-heightgain'] = ss.max(heightgains);
        this.track.upwinds['mean-heightgain'] = ss.mean(heightgains);
        this.track.upwinds['median-heightgain'] = ss.median(heightgains);
        this.track.upwinds['heightgain-deviation'] = ss.standardDeviation(heightgains);
        if (heightgains.length > 1) {
          this.track.upwinds['heightgain-variance'] = ss.sampleVariance(heightgains);
        }


        if (gaps.length > 0) {
          this.track.upwinds['min-gap'] = ss.min(gaps);
          this.track.upwinds['max-gap'] = ss.max(gaps);
          this.track.upwinds['mean-gap'] = ss.mean(gaps);
          this.track.upwinds['median-gap'] = ss.median(gaps);
          this.track.upwinds['gap-deviation'] = ss.standardDeviation(gaps);
          if (gaps.length > 1) {
            this.track.upwinds['gap-variance'] = ss.sampleVariance(gaps);
          }
        }
      }

      // Calculate sink stats:
      this.track.sinks = {};
      this.track.sinks.cnt = this.sinks.length;

      if (this.track.sinks.cnt > 0) {
        this.track.sinks['per-hour'] = this.track.sinks.cnt / (this.track.duration / 60 / 60);

        let climbs = [];
        let durations = [];
        let heightlosts = [];
        let gaps = [];
        last = [];
        for (let t of this.sinks) {
          climbs.push(t['max-sink']);
          durations.push(t.duration);
          heightlosts.push(t.heightlost);
          if (last.length > 0) {
            gaps.push(t.start.time.t - last.end.time.t);
          }
          last = t;
        }

        this.track.sinks['min-sink'] = ss.min(climbs);
        this.track.sinks['max-sink'] = ss.max(climbs);
        this.track.sinks['mean-sink'] = ss.mean(climbs);
        this.track.sinks['median-sink'] = ss.median(climbs);
        this.track.sinks['sink-deviation'] = ss.standardDeviation(climbs);
        if (climbs.length > 1) {
          this.track.sinks['sink-variance'] = ss.sampleVariance(climbs);
        }


        this.track.sinks['sum-duration'] = ss.sum(durations);
        this.track.sinks['min-duration'] = ss.min(durations);
        this.track.sinks['max-duration'] = ss.max(durations);
        this.track.sinks['mean-duration'] = ss.mean(durations);
        this.track.sinks['median-duration'] = ss.median(durations);
        this.track.sinks['duration-deviation'] = ss.standardDeviation(durations);
        if (durations.length > 1) {
          this.track.sinks['duration-variance'] = ss.sampleVariance(durations);
        }


        this.track.sinks['sum-heightlost'] = ss.sum(heightlosts);
        this.track.sinks['min-heightlost'] = ss.min(heightlosts);
        this.track.sinks['max-heightlost'] = ss.max(heightlosts);
        this.track.sinks['mean-heightlost'] = ss.mean(heightlosts);
        this.track.sinks['median-heightlost'] = ss.median(heightlosts);
        this.track.sinks['heightlost-deviation'] = ss.standardDeviation(heightlosts);
        if (heightlosts.length > 1) {
          this.track.sinks['heightlost-variance'] = ss.sampleVariance(heightlosts);
        }


        if (gaps.length > 0) {
          this.track.sinks['min-gap'] = ss.min(gaps);
          this.track.sinks['max-gap'] = ss.max(gaps);
          this.track.sinks['mean-gap'] = ss.mean(gaps);
          this.track.sinks['median-gap'] = ss.median(gaps);
          this.track.sinks['gap-deviation'] = ss.standardDeviation(gaps);
          if (gaps.length > 1) {
            this.track.sinks['gap-variance'] = ss.sampleVariance(gaps);
          }

        }
      }

      // Windspeeds & cloud bases:

      let wspeeds = [];
      let wbearings = [];

      let basealtitudes = [];

      for (let t of this.thermals) {
        if (t === undefined) continue;

        if (t.duration > 60 && t.heightgain > 100) {

          let speed = (t.distance / 1000) / (t.duration / 60 / 60) * this.config['wind-speed-correction-factor'];

          this.windspeeds.push({
            'time': Math.round((t.end.time.t - t.start.time.t) / 2),
            'duration': t.duration,
            'heightgain': t.heightgain,
            'distance': t.distance,
            'bearing': t.bearing,
            'windspeed': speed
          });

          wspeeds.push(speed);
          wbearings.push(t.bearing);

        }

        if (t.heightgain > 250) {

          let alt = t.end.altitude;

          this.bases.push({
            'time': t.end.time.t,
            'altitude': alt
          });

          basealtitudes.push(alt);

        }

      }
      this.track.windspeeds = {};
      if (wspeeds.length > 0) {
        this.track.windspeeds['min-speed'] = ss.min(wspeeds);
        this.track.windspeeds['max-speed'] = ss.max(wspeeds);
        this.track.windspeeds['mean-speed'] = ss.mean(wspeeds);
        this.track.windspeeds['median-speed'] = ss.median(wspeeds);
        this.track.windspeeds['speed-deviation'] = ss.standardDeviation(wspeeds);
        if (wspeeds.length > 1) {
          this.track.windspeeds['speed-variance'] = ss.sampleVariance(wspeeds);
        }

      }

      if (wbearings.length > 0) {
        this.track.windspeeds['min-bearing'] = ss.min(wbearings);
        this.track.windspeeds['max-bearing'] = ss.max(wbearings);
        this.track.windspeeds['mean-bearing'] = ss.mean(wbearings);
        this.track.windspeeds['median-bearing'] = ss.median(wbearings);
        this.track.windspeeds['bearing-deviation'] = ss.standardDeviation(wbearings);
        if (wbearings.length > 1) {
          this.track.windspeeds['bearing-variance'] = ss.sampleVariance(wbearings);
        }

      }
      this.track.bases = {};
      if (basealtitudes.length > 0) {
        this.track.bases['min-altitude'] = ss.min(basealtitudes);
        this.track.bases['max-altitude'] = ss.max(basealtitudes);
        this.track.bases['mean-altitude'] = ss.mean(basealtitudes);
        this.track.bases['median-altitude'] = ss.median(basealtitudes);
        this.track.bases['altitude-deviation'] = ss.standardDeviation(basealtitudes);
        if (basealtitudes.length > 1) {
          this.track.bases['altitude-variance'] = ss.sampleVariance(basealtitudes);
        }
      }

      // General stats:

      let tgain = 0;
      if (typeof this.track.thermals['sum-heightgain'] !== 'undefined') tgain = this.track.thermals['sum-heightgain'];

      let ugain = 0;
      if (typeof this.track.upwinds['sum-heightgain'] !== 'undefined') ugain = this.track.upwinds['sum-heightgain'];

      let lost = 0;
      if (typeof this.track.sinks['sum-heightlost'] !== 'undefined') lost = this.track.sinks['sum-heightlost'];
      this.track.general = {};
      if (lost > 0) {
        this.track.general['gain-vs-lost'] = (tgain + ugain) / lost;
      } else {
        this.track.general['gain-vs-lost'] = 1;
      }

      let progressvalues = [];
      for (let f of this.fixes) {
        progressvalues.push(f.progress);
      }

      if (progressvalues.length > 0) {
        this.track.general['mean-progress'] = ss.mean(progressvalues);
        this.track.general['median-progress'] = ss.median(progressvalues);
        this.track.general['min-progress'] = ss.min(progressvalues);
        this.track.general['max-progress'] = ss.max(progressvalues);
      } else {
        this.track.general['mean-progress'] = 0;
        this.track.general['median-progress'] = 0;
        this.track.general['min-progress'] = 0;
        this.track.general['max-progress'] = 0;
      }

    }
    //console.log(JSON.stringify(this.track.thermals, null, 4));
    //console.log(this.track.thermals);
    return {
      'metadata': this.metadata,
      'track': this.track,
      'route': this.route,
      'fixes': this.fixes,
      'fix-additions': this.fixadditions,
      'thermals': this.thermals,
      'upwinds': this.upwinds,
      'windspeeds': this.windspeeds,
      'bases': this.bases,
      'sinkareas': this.sinks
    };

  }


  // A records: Manufacturer code and unique ID for the individual FR

  parseARecord(data) {

    let manufacturer = data.substr(0, 3);
    let uid = data.substr(3, 3);

    this.metadata.gps = {
      'manufacturer': manufacturer,
      'uid': uid
    };

  }


  // H records: Header records containing metadata information

  parseHRecord(data) {

    let datasource = data.substr(0, 1);
    let subtype = data.substr(1, 3);
    let title, value;

    data = data.substr(4);

    if (data.indexOf(':') > -1) {

      let datasplit = data.split(':');

      title = _.trim(datasplit[0]);
      value = _.trim(datasplit[1]);

    } else {

      title = '';
      value = data;

    }

    // Date:

    if (subtype == 'DTE') {
      this.metadata.date = value;
    }

    // Fix accuracy:

    if (subtype == 'FXA') {
      this.metadata['fix-accuracy'] = Number(value.substr(0, 3));
    }

    // Pilot:

    if (subtype == 'PLT') {
      this.metadata.pilot = value;
    }

    // Glider type:

    if (subtype == 'GTY') {
      this.metadata['glider-type'] = value;
    }

    // Glider id:

    if (subtype == 'GID') {
      this.metadata['glider-id'] = value;
    }

    // Geodetic datum:

    if (subtype == 'DTM') {
      this.metadata['geodetic-datum'] = value;
    }

    // Firmware version:

    if (subtype == 'RFW') {
      this.metadata['firmware-version'] = value;
    }

    // Hardware version:

    if (subtype == 'RHW') {
      this.metadata['hardware-version'] = value;
    }

    // Instrument name:

    if (subtype == 'FTY') {
      this.metadata['instrument-name'] = value;
    }

    // Pressure sensor:

    if (subtype == 'PRS') {
      this.metadata['pressure-sensor'] = value;
    }

  }


  // I records: Definition of additions to B-records

  parseIRecord(data) {

    let cnt = Number(data.substr(0, 2));
    let start = Number(data.substr(2, 2));
    let end = Number(data.substr(4, 2));
    let code = data.substr(6, 3);

    this.fixadditions.push({
      'code': code,
      'start': start,
      'end': end,
      'cnt': cnt
    });

  }


  // B records: Fix records (lat/long/alt etc.)

  parseBRecord(data, extracalculations, filter) {

    let time = data.substr(0, 6);
    let lat = data.substr(6, 8);
    let lng = data.substr(14, 9);
    let validity = data.substr(23, 1);
    let pressurealt = Number(data.substr(24, 5));
    let gpsalt = Number(data.substr(29, 5));

    let altitude = pressurealt;
    if (pressurealt == 0) {
      altitude = gpsalt;
    }

    let hours = Number(time.substr(0, 2));
    let minutes = Number(time.substr(2, 2));
    let seconds = Number(time.substr(4, 2));

    let timestamp = seconds + (minutes * 60) + (hours * 60 * 60);

    if ((_.has(this, 'lastfix.time.t') && timestamp - this.lastfix.time.t >= this.minStepDuration) || !_.has(this, 'lastfix.time.t')) {

      let fix = {
        'time': {
          'h': hours,
          'm': minutes,
          's': seconds,
          't': timestamp
        },
        'lat': this.parseBRecordLatitude(lat),
        'lng': this.parseBRecordLongitude(lng),
        'altitude': altitude,
        'gpsalt': gpsalt,
        'pressalt': pressurealt,
        'extra': []
      };

      // Add fix additions:

      for (let fadd of this.fixadditions) {
        let value = data.substr(fadd.start - 2, fadd.end - fadd.start + 1);
        fix.extra[fadd.code] = value;
      }

      // Filter out the new fix if needed:

      let fixIsOk = true;

      if (filter) {

        // Filter out outliers

        if (this.lastfix.length > 0) {

          // Vertical variation filter:

          duration = fix.time.t - this.lastfix.time.t;
          heightgain = fix.altitude - this.lastfix.altitude;
          vario = 0;
          if (duration > 0) vario = heightgain / duration;
          if (vario > this.config['max-vario'] || vario < this.config['min-vario']) {
            fixIsOk = false;
          }

          // Horizontal variation filter:

          distance = this.distance(fix.lat, fix.lng, this.lastfix.lat, this.lastfix.lng, "K") * 1000;
          speed = 0;
          if (duration > 0) speed = distance / duration * 3.6;
          if (speed > this.config['max-speed'] || (duration > 60 && speed > (this.config['max-speed'] / 100 * 75))) {
            fixIsOk = false;
          }

        }

        // Filter out fixes pre takeoff

        if (this.lastfix.length > 0 && this.nochange) {

          if (fix.lat != this.lastfix.lat || fix.lng != this.lastfix.lng || fix.altitude != this.lastfix.altitude) {
            this.nochange = false;
          } else {
            fixIsOk = false;
          }

        }

      }

      // Do some extra calculations if needed:

      if (extracalculations && fixIsOk) {

        // Get the route:

        if (this.route.takeoff.length === 0) {
          this.route.takeoff = {
            'lat': fix.lat,
            'lng': fix.lng,
            'alt': fix.altitude,
            'h': fix.time.h,
            'm': fix.time.m,
            's': fix.time.s
          };
        }

        this.route.landing = {
          'lat': fix.lat,
          'lng': fix.lng,
          'alt': fix.altitude,
          'h': fix.time.h,
          'm': fix.time.m,
          's': fix.time.s
        };

        // Height gain:

        fix.heightgain = 0;
        if (typeof this.lastfix.altitude !== 'undefined') {
          fix.heightgain = fix.altitude - this.lastfix.altitude;

          if (fix.altitude > this.track.maxaltitude) {
            this.track.maxaltitude = fix.altitude;
          }

          if (fix.altitude < this.track.minaltitude) {
            this.track.minaltitude = fix.altitude;
          }

          if ((fix.altitude - this.track.minaltitude) > this.track.maxaltitudegain) {
            this.track.maxaltitudegain = fix.altitude - this.track.minaltitude;
          }

        }

        // Duration:

        fix.duration = 0;
        if (_.has(this, 'lastfix.time.t')) {
          fix.duration = fix.time.t - this.lastfix.time.t;
          this.track.duration = this.track.duration + fix.duration;
        }

        // Vario:

        fix.vario = 0;
        if (fix.duration != 0) {

          fix.vario = fix.heightgain / fix.duration;

          if (fix.vario > this.track.maxclimb) {
            this.track.maxclimb = fix.vario;
          }

          if (fix.vario < this.track.maxsink) {
            this.track.maxsink = fix.vario;
          }

        }

        // Distance & bearing:

        fix.distance = 0;
        fix.bearing = 0;
        fix['turn-angle'] = 0;
        fix['turn-dir'] = '';
        if (_.has(this, 'lastfix.lat') && _.has(this, 'lastfix.lng')) {

          fix.distance = this.distance(fix.lat, fix.lng, this.lastfix.lat, this.lastfix.lng, "K") * 1000;
          if (isNaN(fix.distance)) fix.distance = 0;

          this.track.distance = this.track.distance + fix.distance;

          fix.bearing = this.bearing(this.lastfix.lat, this.lastfix.lng, fix.lat, fix.lng);

          fix['turn-angle'] = this.abs_bearing_difference(fix.bearing, this.lastfix.bearing);

          if (fix.bearing - this.lastfix.bearing == 0) {
            fix['turn-dir'] = '';
          } else if (fix.bearing > this.lastfix.bearing && fix.bearing - this.lastfix.bearing <= 180) {
            fix['turn-dir'] = 'R';
          } else if (fix.bearing < this.lastfix.bearing && (360 - this.lastfix.bearing) + fix.bearing <= 180) {
            fix['turn-dir'] = 'R';
          } else {
            fix['turn-dir'] = 'L';
          }
        }

        // Calculate a progress value from distance and heightgain:

        fix.progress = 0;
        if (fix.duration > 0) {
          fix.progress = (fix.distance + fix.heightgain * this.config['progress-glide-angle']) / fix.duration;
        }

        // Speed:

        fix.speed = 0;
        if (fix.duration > 0) {

          fix.speed = fix.distance / fix.duration * 3.6;

          if (fix.speed > this.track.maxspeed) {
            this.track.maxspeed = fix.speed;
          }

        }

        // Are we turning or gliding? (get the last 10 seconds and calculate the variation in bearing)

        let bearings = [];
        let minheight = 1000000;
        let maxheight = -1000000;

        let testfixes = [fix];
        for (let i = this.fixes.length - 1; i >= 0; i--) {
          let duration = fix.time.t - this.fixes[i].time.t;
          testfixes.push(this.fixes[i]);
          if (duration >= this.config['min-turning-detection-duration']) break;
        }

        for (let f of testfixes) {
          if (f.altitude > maxheight) maxheight = f.altitude;
          if (f.altitude < minheight) minheight = f.altitude;
          bearings.push(f.bearing);
        }

        let bearingDeviation = ss.standardDeviation(bearings);
        let maxBearingDifference = this.bearing_difference(bearings, this.config['min-turning-bearing-difference']);
        fix['bearing-deviation'] = bearingDeviation;

        if (bearingDeviation > this.config['min-turning-bearing-deviation'] && maxBearingDifference >= this.config['min-turning-bearing-difference']) {
          fix.status = 't';
        } else {
          fix.status = 'g';
        }

      }

      // Add fix:

      if (fixIsOk) {
        this.fixes.push(fix);
        this.lastfix = fix;
      }

    }

  }


  // B record latitude parser:

  parseBRecordLatitude(str) {
    let degree = Number(str.substr(0, 2));
    let min = str.substr(2, 2) + '.' + str.substr(4, 3);
    min = Number(min);

    if (str.substr(-1, 1) === 'S') {
      return this.DMStoDEC(degree, min, 0) * -1;
    } else {
      return this.DMStoDEC(degree, min, 0);
    }
  }


  // B record longitude parser:

  parseBRecordLongitude(str) {
    let degree = Number(str.substr(0, 3));
    let min = str.substr(3, 2) + '.' + str.substr(5, 3);
    min = Number(min);

    if (str.substr(-1, 1) === 'W') {
      return this.DMStoDEC(degree, min, 0) * -1;
    } else {
      return this.DMStoDEC(degree, min, 0);
    }
  }


  // DMS (degree, minutes, seconds) to DEC (decimal) coordinate value parser:

  DMStoDEC(deg, min, sec) {
    return deg + (((min * 60) + (sec)) / 3600);
  }


  /*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
  /*::                                                                         :*/
  /*::  This routine calculates the distance between two points (given the     :*/
  /*::  latitude/longitude of those points). It is being used to calculate     :*/
  /*::  the distance between two locations using GeoDataSource(TM) Products    :*/
  /*::                                                                         :*/
  /*::  Definitions:                                                           :*/
  /*::    South latitudes are negative, east longitudes are positive           :*/
  /*::                                                                         :*/
  /*::  Passed to function:                                                    :*/
  /*::    lat1, lon1 = Latitude and Longitude of point 1 (in decimal degrees)  :*/
  /*::    lat2, lon2 = Latitude and Longitude of point 2 (in decimal degrees)  :*/
  /*::    unit = the unit you desire for results                               :*/
  /*::           where: 'M' is statute miles (default)                         :*/
  /*::                  'K' is kilometers                                      :*/
  /*::                  'N' is nautical miles                                  :*/
  /*::  Worldwide cities and other features databases with latitude longitude  :*/
  /*::  are available at http://www.geodatasource.com                          :*/
  /*::                                                                         :*/
  /*::  For enquiries, please contact sales@geodatasource.com                  :*/
  /*::                                                                         :*/
  /*::  Official Web site: http://www.geodatasource.com                        :*/
  /*::                                                                         :*/
  /*::         GeoDataSource.com (C) All Rights Reserved 2015		   		     :*/
  /*::                                                                         :*/
  /*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
  distance(lat1, lon1, lat2, lon2, unit) {

    let theta = lon1 - lon2;
    let dist = Math.sin(this.deg2rad(lat1)) * Math.sin(this.deg2rad(lat2)) + Math.cos(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) * Math.cos(this.deg2rad(theta));
    dist = Math.acos(dist);
    dist = this.rad2deg(dist);
    let miles = dist * 60 * 1.1515;
    unit = unit.toUpperCase();

    if (unit == "K") {
      return (miles * 1.609344);
    } else if (unit == "N") {
      return (miles * 0.8684);
    } else {
      return miles;
    }
  }

  rad2deg(angle) {
    return angle * 57.29577951308232 // angle / Math.PI * 180
  }

  deg2rad(angle) {
    return angle * 0.017453292519943295 // (angle / 180) * Math.PI;
  }

  // Bearing methods:


  bearing(lat1, lon1, lat2, lon2) {
    return (this.rad2deg(Math.atan2(Math.sin(this.deg2rad(lon2) - this.deg2rad(lon1)) * Math.cos(this.deg2rad(lat2)), Math.cos(this.deg2rad(lat1)) * Math.sin(this.deg2rad(lat2)) - Math.sin(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) * Math.cos(this.deg2rad(lon2) - this.deg2rad(lon1)))) + 360) % 360;
  }

  abs_bearing_difference(a, b) {
    return 180 - Math.abs(180 - Math.abs(a - b));
  }

  // Calculate the biggest difference in a sample of bearings:

  array_keys(myObject) {
    let output = [];
    for (let key in myObject) {
      output.push(key);
    }
    return output;
  }

  bearing_difference(samples, maxdiff = 180) {
    let max = 0;
    let uniqueSamples = [];

    for (let s of samples) {
      uniqueSamples[Number(s)] = true;
    }
    uniqueSamples = this.array_keys(uniqueSamples);

    outer: for (let s1 of uniqueSamples) {
      for (let s2 of uniqueSamples) {
        let diff = this.abs_bearing_difference(s1, s2);
        if (diff > max) {
          max = diff;
          if (max >= maxdiff) break outer;
        }
      }
    }

    return max;

  }


}

module.exports = IGCAnalyzer;
