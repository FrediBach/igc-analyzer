<?php


class IGCParser {

	private $config = array(
		'min-thermal-duration' 				=> 20,
		'min-upwind-duration' 				=> 20,
		'min-sinkarea-vario' 				=> -1.5,
		'min-sinkarea-duration' 			=> 20,
		'max-thermal-separation' 			=> 20,
		'max-upwind-separation' 			=> 20,
		'max-sinkarea-separation' 			=> 20,
		'max-vario' 						=> 25,
		'min-vario' 						=> -25,
		'max-speed' 						=> 150,
		'progress-glide-angle' 				=> 8,
		'min-turning-detection-duration' 	=> 15,
		'min-turning-bearing-difference' 	=> 45,
		'min-turning-bearing-deviation' 	=> 30,
		'wind-speed-correction-factor'		=> 2
	);
	
	private $lines = array(); 			// all lines in the igc file
	private $fixes = array(); 			// all gps fixes
	private $metadata = array(); 		// all the metadata found
	private $fixadditions = array(); 	// all additional data to be found in a fix
	
	private $lastfix = array(); 		// Last fix used to calculate new fix values
	private $minStepDuration = 1;		// In seconds, duration between saved fixes
	
	private $thermals = array();		// All major thermals, with metadata
	private $currentThermal = array();	// Data of the current thermal
	
	private $upwinds = array();			// All major upwind (soaring) areas
	private $currentUpwind = array();	// Current upwind area
	
	private $sinks = array();			// All major sink areas, with metadata
	private $currentSink = array();		// Data of the current sink area
	
	private $windspeeds = array();		// All windspeeds (taken from thermals)
	
	private $bases = array();			// Base heights (taken from thermals)
	
	private $nochange = true;			// Tracks changes between fixes
	
	private $track = array(				// Overall track statistics
		'distance' => 0,
		'maxaltitude' => -1000000,
		'minaltitude' => 1000000,
		'maxaltitudegain' => 0,
		'maxclimb' => 0,
		'maxsink' => 0,
		'maxspeed' => 0,
		'duration' => 0,
		'gliding-duration' => 0,
		'thermaling-duration' => 0,
		'left-thermaling-duration' => 0,
		'right-thermaling-duration' => 0,
		'thermals' => array(),
		'upwinds' => array(),
		'sinks' => array(),
		'windspeeds' => array(),
		'bases' => array(),
		'general' => array()
	);
	
	private $route = array(				// The route takeoff, landing and turnpoints
		'takeoff' => array(),
		'landing' => array(),
		'route' => array()
	);
	
	public $codes = array(				// IGC three letter codes
		'ACX' => 'Linear accelerations X axis',
		'ACY' => 'Linear accelerations Y axis',
		'ACZ' => 'Linear accelerations Z axis',
		'ANX' => 'Angular accelerations X axis',
		'ANY' => 'Angular accelerations Y axis',
		'ANZ' => 'Angular accelerations Z axis',
		'ATS' => 'Altimeter pressure setting',
		'BFI' => 'Blind Flying Instrument',
		'CCL' => 'Competition class',
		'CCN' => 'Camera Connect',
		'CCO' => 'Compass course',
		'CDC' => 'Camera Disconnect',
		'CGD' => 'Change of geodetic datum',
		'CID' => 'Competition ID',
		'CLB' => 'Club or organisation, and country, from which flown or operated',
		'CM2' => 'Second Crew Member\'s Name',
		'DAE' => 'Displacement east',
		'DAN' => 'Displacement north',
		'DB1' => 'Date of Birth of the pilot-in-charge',
		'DB2' => 'Date of Birth of second crew member',
		'DOB' => 'Date of Birth of the pilot',
		'DTE' => 'Date',
		'DTM' => 'Geodetic Datum',
		'EDN' => 'Engine down',
		'ENL' => 'Environmental Noise Level',
		'EOF' => 'Engine off',
		'EON' => 'Engine on',
		'EUP' => 'Engine up',
		'FIN' => 'Finish',
		'FLP' => 'Flap position',
		'FRS' => 'Flight Recorder Security',
		'FTY' => 'Flight Recorder Type',
		'FXA' => 'Fix accuracy',
		'GAL' => 'Galileo System',
		'GCN' => 'GNSS Connect',
		'GDC' => 'GNSS Disconnect',
		'GID' => 'Glider ID',
		'GLO' => 'GLONASS System',
		'GPS' => 'GPS System',
		'GSP' => 'Groundspeed',
		'GTY' => 'Glider type',
		'HDM' => 'Heading Magnetic',
		'HDT' => 'Heading True',
		'IAS' => 'Airspeed',
		'LAD' => 'The last places of decimal minutes of latitude',
		'LOD' => 'The last places of decimal minutes of longitude',
		'LOV' => 'Low voltage',
		'MAC' => 'MacCready setting',
		'MOP' => 'Means of Propulsion',
		'OA1' => 'Position of other aircraft',
		'OA2' => 'Position of other aircraft',
		'OA3' => 'Position of other aircraft',
		'OA4' => 'Position of other aircraft',
		'OA5' => 'Position of other aircraft',
		'OAT' => 'Outside air temperature',
		'ONT' => 'On Task',
		'OOI' => 'OO ID',
		'PEV' => 'Pilot EVent',
		'PFC' => 'Post-Flight Claim',
		'PHO' => 'Photo taken',
		'PLT' => 'Pilot-in-charge',
		'PRS' => 'Pressure Altitude Sensor',
		'RAI' => 'RAIM',
		'REX' => 'Record addition',
		'RFW' => 'Firmware Revision Version of FR',
		'RHW' => 'Hardware Revision Version of FR',
		'SCM' => 'Second Crew Member\'s Name',
		'SEC' => 'Security',
		'SIT' => 'Site',
		'SIU' => 'Satellites in use',
		'STA' => 'Start event',
		'TAS' => 'Airspeed True',
		'TDS' => 'Decimal seconds of UTC time',
		'TEN' => 'Total Energy Altitude',
		'TPC' => 'Turn point confirmation',
		'TRM' => 'Track Magnetic',
		'TRT' => 'Track True',
		'TZN' => 'Time Zone Offset',
		'UND' => 'Undercarriage',
		'UNT' => 'Units of Measure',
		'VAR' => 'Uncompensated variometer',
		'VAT' => 'Compensated variometer',
		'VXA' => 'Vertical Fix Accuracy',
		'WDI' => 'Wind Direction',
		'WSP' => 'Wind speed'
	);
	
	function __construct($data){
		$this->lines = explode("\n",trim($data));
	}
	
	
	// Set minimum fix duration:
	
	public function setMinStepDuration($value){
		$this->minStepDuration = (int)$value;
	}
	
	
	// The main parse method:
	
	public function parse($extracalculations = false, $filter = false){
	
		foreach($this->lines as $line){
			
			$line = trim($line);
			$type = strtoupper(substr($line, 0, 1));
			
			$linedata = substr($line, 1);
			
			switch ($type) {
			    case 'A':
			        $this->parseARecord($linedata);
			        break;
			    case 'H':
			        $this->parseHRecord($linedata);
			        break;
			    case 'I':
			        $this->parseIRecord($linedata);
			        break;
			    case 'B':
			        $this->parseBRecord($linedata, $extracalculations, $filter);
			        break;
			}
			
		}
		
		if ($filter){
			
			// Remove unneaded entries after landing
			
			$last = array();
			for($i=count($this->fixes)-1; $i >= 0; $i--){
			
				$fix = $this->fixes[$i];
				
				if (!empty($last)){
					if ($fix['lat'] != $last['lat'] || $fix['lng'] != $last['lng'] || $fix['altitude'] != $last['altitude']){
					
						$this->fixes = array_slice($this->fixes, 0, $i+2);
						
						$this->route['landing'] = array(
							'lat' => $this->fixes[$i+1]['lat'],
							'lng' => $this->fixes[$i+1]['lng'],
							'alt' => $this->fixes[$i+1]['altitude'],
							'h' => $this->fixes[$i+1]['time']['h'],
							'm' => $this->fixes[$i+1]['time']['m'],
							's' => $this->fixes[$i+1]['time']['s']
						);
						
						$this->track['duration'] = ($this->route['landing']['s'] + $this->route['landing']['m']*60 + $this->route['landing']['h']*60*60) - ($this->route['takeoff']['s'] + $this->route['takeoff']['m']*60 + $this->route['takeoff']['h']*60*60);
						
						break;
						
					}
				}
				
				$last = $fix;
				
			}
			
		}
		
		if ($extracalculations){
		
			// Because we need multiple samples, status is alway 10 seconds delayed, so fix that first:
			
			$steps = 2; // <- make this dynamic!
			
			for ($i=0; $i<count($this->fixes); $i++){
				if (isset($this->fixes[$i-$steps])){
					$this->fixes[$i-$steps]['status'] = $this->fixes[$i]['status'];
				}
			}
			
			// Now do the extra calculations:
			
			foreach ($this->fixes as $f){
			
				if ($f['status'] == 'g') $this->track['gliding-duration'] = $this->track['gliding-duration'] + $f['duration'];
				if ($f['status'] == 't'){
					$this->track['thermaling-duration'] = $this->track['thermaling-duration'] + $f['duration'];
					if ($f['turn-dir'] == 'L') $this->track['left-thermaling-duration'] =  $this->track['left-thermaling-duration'] + $f['duration'];
					if ($f['turn-dir'] == 'R') $this->track['right-thermaling-duration'] =  $this->track['right-thermaling-duration'] + $f['duration'];
				}
				
				// Thermal detection:
				
				if ($f['status'] == 't'){
					
					// We're turning, maybe we're in a thermal. Add fix to the current thermal if it's one:
					
					if (empty($this->currentThermal)){
						$this->currentThermal['start'] = $f;
						$this->currentThermal['min-climb'] = $f['vario'];
						$this->currentThermal['max-climb'] = $f['vario'];
					} else {
						if ($f['vario'] < $this->currentThermal['min-climb']){
							$this->currentThermal['min-climb'] = $f['vario'];
						}
						if ($f['vario'] > $this->currentThermal['max-climb']){
							$this->currentThermal['max-climb'] = $f['vario'];
						}
					}
					
					$this->currentThermal['end'] = $f;
					
				} else {
					
					if (!empty($this->currentThermal)){
					
						// We're gliding, maybe we left a thermal? If yes, and if the current thermal is big enough, add it to the thermals array:
						
						$this->currentThermal['duration'] = $this->currentThermal['end']['time']['t'] - $this->currentThermal['start']['time']['t'];
						$this->currentThermal['heightgain'] = $this->currentThermal['end']['altitude'] - $this->currentThermal['start']['altitude'];
						
						if ($this->currentThermal['duration'] > $this->config['min-thermal-duration'] && $this->currentThermal['heightgain'] > 0){
							
							$this->currentThermal['climbrate'] = $this->currentThermal['heightgain'] / $this->currentThermal['duration'];
							$this->currentThermal['distance'] = $this->distance($this->currentThermal['start']['lat'], $this->currentThermal['start']['lng'], $this->currentThermal['end']['lat'], $this->currentThermal['end']['lng'], "K") * 1000;
							$this->currentThermal['bearing'] = $this->bearing($this->currentThermal['start']['lat'], $this->currentThermal['start']['lng'], $this->currentThermal['end']['lat'], $this->currentThermal['end']['lng']);
							
							$this->thermals[] = $this->currentThermal;
						
						}
						
					}
					
					$this->currentThermal = array();
					
				}
				
				// Upwind detection:
				
				if ($f['status'] == 'g' && $f['vario'] >= 0){
					
					// We're gliding and not sinking, maybe we're in an upwind. Add fix to the current upwind if it's one:
					
					if (empty($this->currentUpwind)){
						$this->currentUpwind['start'] = $f;
						$this->currentUpwind['max-climb'] = $f['vario'];
					} else {
						if ($f['vario'] > $this->currentUpwind['max-climb']){
							$this->currentUpwind['max-climb'] = $f['vario'];
						}
					}
					
					$this->currentUpwind['end'] = $f;
					
				} else {
					
					if (!empty($this->currentUpwind)){
					
						// We're gliding, maybe we left a thermal? If yes, and if the current thermal is big enough, add it to the thermals array:
						
						$this->currentUpwind['duration'] = $this->currentUpwind['end']['time']['t'] - $this->currentUpwind['start']['time']['t'];
						$this->currentUpwind['heightgain'] = $this->currentUpwind['end']['altitude'] - $this->currentUpwind['start']['altitude'];
						
						if ($this->currentUpwind['duration'] > $this->config['min-upwind-duration'] && $this->currentUpwind['heightgain'] > 0){
							
							$this->currentUpwind['climbrate'] = $this->currentUpwind['heightgain'] / $this->currentUpwind['duration'];
							$this->currentUpwind['distance'] = $this->distance($this->currentUpwind['start']['lat'], $this->currentUpwind['start']['lng'], $this->currentUpwind['end']['lat'], $this->currentUpwind['end']['lng'], "K") * 1000;
							$this->currentUpwind['bearing'] = $this->bearing($this->currentUpwind['start']['lat'], $this->currentUpwind['start']['lng'], $this->currentUpwind['end']['lat'], $this->currentUpwind['end']['lng']);
							
							$this->upwinds[] = $this->currentUpwind;
						
						}
						
					}
					
					$this->currentUpwind = array();
					
				}
				
				// Sink area detection:
				
				if ($f['status'] == 'g' && $f['vario'] <= $this->config['min-sinkarea-vario']){
					
					// We're gliding in sink, maybe we're in a sink area. Add fix to the current sink if it's one:
					
					if (empty($this->currentSink)){
						$this->currentSink['start'] = $f;
						$this->currentSink['max-sink'] = $f['vario'];
					} else {
						if ($f['vario'] < $this->currentSink['max-sink']){
							$this->currentSink['max-sink'] = $f['vario'];
						}
					}
					
					$this->currentSink['end'] = $f;
					
				} else {
					
					if (!empty($this->currentSink)){
					
						// We're probably out of sink. If yes, and if the current sink area is big enough, add it to the sinks array:
						
						$this->currentSink['duration'] = $this->currentSink['end']['time']['t'] - $this->currentSink['start']['time']['t'];
						$this->currentSink['heightlost'] = $this->currentSink['start']['altitude'] - $this->currentSink['end']['altitude'];
						
						if ($this->currentSink['duration'] > $this->config['min-sinkarea-duration'] && $this->currentSink['heightlost'] > 0){
							
							$this->currentSink['sinkrate'] = $this->currentSink['heightlost'] / $this->currentSink['duration'] * -1;
							$this->currentSink['distance'] = $this->distance($this->currentSink['start']['lat'], $this->currentSink['start']['lng'], $this->currentSink['end']['lat'], $this->currentSink['end']['lng'], "K") * 1000;
							$this->currentSink['bearing'] = $this->bearing($this->currentSink['start']['lat'], $this->currentSink['start']['lng'], $this->currentSink['end']['lat'], $this->currentSink['end']['lng']);
							
							$this->sinks[] = $this->currentSink;
						
						}
						
					}
					
					$this->currentSink = array();
					
				}
				
			}
			
			// Combine thermals that are very close together:
			
			$last = array();
			
			$last = 0;
			for ($i = 1; $i < count($this->thermals); $i++){
				
				if ($this->thermals[$i]['start']['time']['t'] - $this->thermals[$i-1]['end']['time']['t'] < $this->config['max-thermal-separation']){
					
					$this->thermals[$last]['end'] = $this->thermals[$i]['end'];
					$this->thermals[$i]['merged'] = true;
					
					$this->thermals[$last]['duration'] = $this->thermals[$last]['end']['time']['t'] - $this->thermals[$last]['start']['time']['t'];
					$this->thermals[$last]['heightgain'] = $this->thermals[$last]['end']['altitude'] - $this->thermals[$last]['start']['altitude'];
					$this->thermals[$last]['climbrate'] = $this->thermals[$last]['heightgain'] / $this->thermals[$last]['duration'];
					$this->thermals[$last]['distance'] = $this->distance($this->thermals[$last]['start']['lat'], $this->thermals[$last]['start']['lng'], $this->thermals[$last]['end']['lat'], $this->thermals[$last]['end']['lng'], "K") * 1000;
					$this->thermals[$last]['bearing'] = $this->bearing($this->thermals[$last]['start']['lat'], $this->thermals[$last]['start']['lng'], $this->thermals[$last]['end']['lat'], $this->thermals[$last]['end']['lng']);
						
				} else {
					$last = $i;
				}
				
			}
			
			foreach($this->thermals as $k => $v){
				if (isset($v['merged'])){
					unset($this->thermals[$k]);
				}
			}
			
			// Combine upwinds that are very close together:
			
			$last = array();
			
			$last = 0;
			for ($i = 1; $i < count($this->upwinds); $i++){
				
				if ($this->upwinds[$i]['start']['time']['t'] - $this->upwinds[$i-1]['end']['time']['t'] < $this->config['max-upwind-separation']){
					
					$this->upwinds[$last]['end'] = $this->upwinds[$i]['end'];
					$this->upwinds[$i]['merged'] = true;
					
					$this->upwinds[$last]['duration'] = $this->upwinds[$last]['end']['time']['t'] - $this->upwinds[$last]['start']['time']['t'];
					$this->upwinds[$last]['heightgain'] = $this->upwinds[$last]['end']['altitude'] - $this->upwinds[$last]['start']['altitude'];
					$this->upwinds[$last]['climbrate'] = $this->upwinds[$last]['heightgain'] / $this->upwinds[$last]['duration'];
					$this->upwinds[$last]['distance'] = $this->distance($this->upwinds[$last]['start']['lat'], $this->upwinds[$last]['start']['lng'], $this->upwinds[$last]['end']['lat'], $this->upwinds[$last]['end']['lng'], "K") * 1000;
					$this->upwinds[$last]['bearing'] = $this->bearing($this->upwinds[$last]['start']['lat'], $this->upwinds[$last]['start']['lng'], $this->upwinds[$last]['end']['lat'], $this->upwinds[$last]['end']['lng']);
						
				} else {
					$last = $i;
				}
				
			}
			
			foreach($this->upwinds as $k => $v){
				if (isset($v['merged'])){
					unset($this->upwinds[$k]);
				}
			}
			
			// Combine sinks that are very close together:
			
			$last = array();
			
			$last = 0;
			for ($i = 1; $i < count($this->sinks); $i++){
				
				if ($this->sinks[$i]['start']['time']['t'] - $this->sinks[$i-1]['end']['time']['t'] < $this->config['max-sinkarea-separation']){
					
					$this->sinks[$last]['end'] = $this->sinks[$i]['end'];
					$this->sinks[$i]['merged'] = true;
					
					$this->sinks[$last]['duration'] = $this->sinks[$last]['end']['time']['t'] - $this->sinks[$last]['start']['time']['t'];
					$this->sinks[$last]['heightlost'] = $this->sinks[$last]['start']['altitude'] - $this->sinks[$last]['end']['altitude'];
					$this->sinks[$last]['sinkrate'] = $this->sinks[$last]['heightlost'] / $this->sinks[$last]['duration'] * -1;
					$this->sinks[$last]['distance'] = $this->distance($this->sinks[$last]['start']['lat'], $this->sinks[$last]['start']['lng'], $this->sinks[$last]['end']['lat'], $this->sinks[$last]['end']['lng'], "K") * 1000;
					$this->sinks[$last]['bearing'] = $this->bearing($this->sinks[$last]['start']['lat'], $this->sinks[$last]['start']['lng'], $this->sinks[$last]['end']['lat'], $this->sinks[$last]['end']['lng']);
						
				} else {
					$last = $i;
				}
				
			}
			
			foreach($this->sinks as $k => $v){
				if (isset($v['merged'])){
					unset($this->sinks[$k]);
				}
			}
			
			// Calculate thermal stats:
			
			$this->track['thermals']['cnt'] = count($this->thermals);
			
			if ($this->track['thermals']['cnt'] > 0){
				$this->track['thermals']['per-hour'] = $this->track['thermals']['cnt'] / ($this->track['duration']/60/60);
				
				$climbs = array();
				$durations = array();
				$heightgains = array();
				$gaps = array();
				$last = array();
				foreach($this->thermals as $t){
					$climbs[] = $t['max-climb'];
					$durations[] = $t['duration'];
					$heightgains[] = $t['heightgain'];
					if (!empty($last)){
						$gaps[] = $t['start']['time']['t'] - $last['end']['time']['t'];
					}
					$last = $t;
				}
				
				$this->track['thermals']['min-climb'] = min($climbs);
				$this->track['thermals']['max-climb'] = max($climbs);
				$this->track['thermals']['mean-climb'] = $this->stats_mean($climbs);
				$this->track['thermals']['median-climb'] = $this->stats_median($climbs);
				$this->track['thermals']['climb-deviation'] = $this->stats_standard_deviation($climbs);
				$this->track['thermals']['climb-variance'] = $this->stats_variance_population($climbs);
				
				$this->track['thermals']['sum-duration'] = array_sum($durations);
				$this->track['thermals']['min-duration'] = min($durations);
				$this->track['thermals']['max-duration'] = max($durations);
				$this->track['thermals']['mean-duration'] = $this->stats_mean($durations);
				$this->track['thermals']['median-duration'] = $this->stats_median($durations);
				$this->track['thermals']['duration-deviation'] = $this->stats_standard_deviation($durations);
				$this->track['thermals']['duration-variance'] = $this->stats_variance_population($durations);
				
				$this->track['thermals']['sum-heightgain'] = array_sum($heightgains);
				$this->track['thermals']['min-heightgain'] = min($heightgains);
				$this->track['thermals']['max-heightgain'] = max($heightgains);
				$this->track['thermals']['mean-heightgain'] = $this->stats_mean($heightgains);
				$this->track['thermals']['median-heightgain'] = $this->stats_median($heightgains);
				$this->track['thermals']['heightgain-deviation'] = $this->stats_standard_deviation($heightgains);
				$this->track['thermals']['heightgain-variance'] = $this->stats_variance_population($heightgains);
				
				if (count($gaps) > 0){
					$this->track['thermals']['min-gap'] = min($gaps);
					$this->track['thermals']['max-gap'] = max($gaps);
					$this->track['thermals']['mean-gap'] = $this->stats_mean($gaps);
					$this->track['thermals']['median-gap'] = $this->stats_median($gaps);
					$this->track['thermals']['gap-deviation'] = $this->stats_standard_deviation($gaps);
					$this->track['thermals']['gap-variance'] = $this->stats_variance_population($gaps);
				}
			}
			
			// Calculate upwind stats:
			
			$this->track['upwinds']['cnt'] = count($this->upwinds);
			
			if ($this->track['upwinds']['cnt'] > 0){
				$this->track['upwinds']['per-hour'] = $this->track['upwinds']['cnt'] / ($this->track['duration']/60/60);
				
				$climbs = array();
				$durations = array();
				$heightgains = array();
				$gaps = array();
				$last = array();
				foreach($this->upwinds as $t){
					$climbs[] = $t['max-climb'];
					$durations[] = $t['duration'];
					$heightgains[] = $t['heightgain'];
					if (!empty($last)){
						$gaps[] = $t['start']['time']['t'] - $last['end']['time']['t'];
					}
					$last = $t;
				}
				
				$this->track['upwinds']['min-climb'] = min($climbs);
				$this->track['upwinds']['max-climb'] = max($climbs);
				$this->track['upwinds']['mean-climb'] = $this->stats_mean($climbs);
				$this->track['upwinds']['median-climb'] = $this->stats_median($climbs);
				$this->track['upwinds']['climb-deviation'] = $this->stats_standard_deviation($climbs);
				$this->track['upwinds']['climb-variance'] = $this->stats_variance_population($climbs);
				
				$this->track['upwinds']['sum-duration'] = array_sum($durations);
				$this->track['upwinds']['min-duration'] = min($durations);
				$this->track['upwinds']['max-duration'] = max($durations);
				$this->track['upwinds']['mean-duration'] = $this->stats_mean($durations);
				$this->track['upwinds']['median-duration'] = $this->stats_median($durations);
				$this->track['upwinds']['duration-deviation'] = $this->stats_standard_deviation($durations);
				$this->track['upwinds']['duration-variance'] = $this->stats_variance_population($durations);
				
				$this->track['upwinds']['sum-heightgain'] = array_sum($heightgains);
				$this->track['upwinds']['min-heightgain'] = min($heightgains);
				$this->track['upwinds']['max-heightgain'] = max($heightgains);
				$this->track['upwinds']['mean-heightgain'] = $this->stats_mean($heightgains);
				$this->track['upwinds']['median-heightgain'] = $this->stats_median($heightgains);
				$this->track['upwinds']['heightgain-deviation'] = $this->stats_standard_deviation($heightgains);
				$this->track['upwinds']['heightgain-variance'] = $this->stats_variance_population($heightgains);
				
				if (count($gaps) > 0){
					$this->track['upwinds']['min-gap'] = min($gaps);
					$this->track['upwinds']['max-gap'] = max($gaps);
					$this->track['upwinds']['mean-gap'] = $this->stats_mean($gaps);
					$this->track['upwinds']['median-gap'] = $this->stats_median($gaps);
					$this->track['upwinds']['gap-deviation'] = $this->stats_standard_deviation($gaps);
					$this->track['upwinds']['gap-variance'] = $this->stats_variance_population($gaps);
				}
			}
			
			// Calculate sink stats:
			
			$this->track['sinks']['cnt'] = count($this->sinks);
			
			if ($this->track['sinks']['cnt'] > 0){
				$this->track['sinks']['per-hour'] = $this->track['sinks']['cnt'] / ($this->track['duration']/60/60);
				
				$climbs = array();
				$durations = array();
				$heightlosts = array();
				$gaps = array();
				$last = array();
				foreach($this->sinks as $t){
					$climbs[] = $t['max-sink'];
					$durations[] = $t['duration'];
					$heightlosts[] = $t['heightlost'];
					if (!empty($last)){
						$gaps[] = $t['start']['time']['t'] - $last['end']['time']['t'];
					}
					$last = $t;
				}
				
				$this->track['sinks']['min-sink'] = min($climbs);
				$this->track['sinks']['max-sink'] = max($climbs);
				$this->track['sinks']['mean-sink'] = $this->stats_mean($climbs);
				$this->track['sinks']['median-sink'] = $this->stats_median($climbs);
				$this->track['sinks']['sink-deviation'] = $this->stats_standard_deviation($climbs);
				$this->track['sinks']['sink-variance'] = $this->stats_variance_population($climbs);
				
				$this->track['sinks']['sum-duration'] = array_sum($durations);
				$this->track['sinks']['min-duration'] = min($durations);
				$this->track['sinks']['max-duration'] = max($durations);
				$this->track['sinks']['mean-duration'] = $this->stats_mean($durations);
				$this->track['sinks']['median-duration'] = $this->stats_median($durations);
				$this->track['sinks']['duration-deviation'] = $this->stats_standard_deviation($durations);
				$this->track['sinks']['duration-variance'] = $this->stats_variance_population($durations);
				
				$this->track['sinks']['sum-heightlost'] = array_sum($heightlosts);
				$this->track['sinks']['min-heightlost'] = min($heightlosts);
				$this->track['sinks']['max-heightlost'] = max($heightlosts);
				$this->track['sinks']['mean-heightlost'] = $this->stats_mean($heightlosts);
				$this->track['sinks']['median-heightlost'] = $this->stats_median($heightlosts);
				$this->track['sinks']['heightlost-deviation'] = $this->stats_standard_deviation($heightlosts);
				$this->track['sinks']['heightlost-variance'] = $this->stats_variance_population($heightlosts);
				
				if (count($gaps) > 0){
					$this->track['sinks']['min-gap'] = min($gaps);
					$this->track['sinks']['max-gap'] = max($gaps);
					$this->track['sinks']['mean-gap'] = $this->stats_mean($gaps);
					$this->track['sinks']['median-gap'] = $this->stats_median($gaps);
					$this->track['sinks']['gap-deviation'] = $this->stats_standard_deviation($gaps);
					$this->track['sinks']['gap-variance'] = $this->stats_variance_population($gaps);
				}
			}
			
			// Windspeeds & cloud bases:
			
			$wspeeds = array();
			$wbearings = array();
			
			$basealtitudes = array();
			
			foreach($this->thermals as $t){
			
				if ($t['duration'] > 60 && $t['heightgain'] > 100){
					
					$speed = ($t['distance']/1000) / ($t['duration']/60/60) * $this->config['wind-speed-correction-factor'];
					
					$this->windspeeds[] = array(
						'time' => round(($t['end']['time']['t'] - $t['start']['time']['t']) / 2),
						'duration' => $t['duration'],
						'heightgain' => $t['heightgain'],
						'distance' => $t['distance'],
						'bearing' => $t['bearing'],
						'windspeed' => $speed
					);
					
					$wspeeds[] = $speed;
					$wbearings[] = $t['bearing'];
					
				}
				
				if ($t['heightgain'] > 250){
				
					$alt = $t['end']['altitude'];
					
					$this->bases[] = array(
						'time' => $t['end']['time']['t'],
						'altitude' => $alt
					);
					
					$basealtitudes[] = $alt;
					
				}
				
			}
			
			if (count($wspeeds) > 0){
				$this->track['windspeeds']['min-speed'] = min($wspeeds);
				$this->track['windspeeds']['max-speed'] = max($wspeeds);
				$this->track['windspeeds']['mean-speed'] = $this->stats_mean($wspeeds);
				$this->track['windspeeds']['median-speed'] = $this->stats_median($wspeeds);
				$this->track['windspeeds']['speed-deviation'] = $this->stats_standard_deviation($wspeeds);
				$this->track['windspeeds']['speed-variance'] = $this->stats_variance_population($wspeeds);
			}
			
			if (count($wbearings) > 0){
				$this->track['windspeeds']['min-bearing'] = min($wbearings);
				$this->track['windspeeds']['max-bearing'] = max($wbearings);
				$this->track['windspeeds']['mean-bearing'] = $this->stats_mean($wbearings);
				$this->track['windspeeds']['median-bearing'] = $this->stats_median($wbearings);
				$this->track['windspeeds']['bearing-deviation'] = $this->stats_standard_deviation($wbearings);
				$this->track['windspeeds']['bearing-variance'] = $this->stats_variance_population($wbearings);
			}
			
			if (count($basealtitudes) > 0){
				$this->track['bases']['min-altitude'] = min($basealtitudes);
				$this->track['bases']['max-altitude'] = max($basealtitudes);
				$this->track['bases']['mean-altitude'] = $this->stats_mean($basealtitudes);
				$this->track['bases']['median-altitude'] = $this->stats_median($basealtitudes);
				$this->track['bases']['altitude-deviation'] = $this->stats_standard_deviation($basealtitudes);
				$this->track['bases']['altitude-variance'] = $this->stats_variance_population($basealtitudes);
			}
			
			// General stats:
			
			$tgain = 0;
			if (isset($this->track['thermals']['sum-heightgain'])) $tgain = $this->track['thermals']['sum-heightgain'];
			
			$ugain = 0;
			if (isset($this->track['upwinds']['sum-heightgain'])) $ugain = $this->track['upwinds']['sum-heightgain'];
			
			$lost = 0;
			if (isset($this->track['sinks']['sum-heightlost'])) $lost = $this->track['sinks']['sum-heightlost'];
			
			if ($lost > 0){
				$this->track['general']['gain-vs-lost'] = ($tgain + $ugain) / $lost;
			} else {
				$this->track['general']['gain-vs-lost'] = 1;
			}
			
			$progressvalues = array();
			foreach ($this->fixes as $f){
				$progressvalues[] = $f['progress'];
			}
			
			if (count($progressvalues) > 0){
				$this->track['general']['mean-progress'] = $this->stats_mean($progressvalues);
				$this->track['general']['median-progress'] = $this->stats_median($progressvalues);
				$this->track['general']['min-progress'] = min($progressvalues);
				$this->track['general']['max-progress'] = max($progressvalues);
			} else {
				$this->track['general']['mean-progress'] = 0;
				$this->track['general']['median-progress'] = 0;
				$this->track['general']['min-progress'] = 0;
				$this->track['general']['max-progress'] = 0;
			}
			
		}
		
		return array(
			'metadata' => $this->metadata, 
			'track' => $this->track, 
			'route' => $this->route, 
			'fixes' => $this->fixes, 
			'fix-additions' => $this->fixadditions, 
			'thermals' => $this->thermals,
			'upwinds' => $this->upwinds,
			'windspeeds' => $this->windspeeds,
			'bases' => $this->bases,
			'sinkareas' => $this->sinks
		);
			
	}
	
	
	// A records: Manufacturer code and unique ID for the individual FR
	
	private function parseARecord($data){
		
		$manufacturer = substr($data, 0, 3);
		$uid = substr($data, 3, 3);
		
		$this->metadata['gps'] = array(
			'manufacturer' => $manufacturer,
			'uid' => $uid
		);
		
	}
	
	
	// H records: Header records containing metadata information
	
	private function parseHRecord($data){
	
		$datasource = substr($data, 0, 1);
		$subtype = substr($data, 1, 3);
		
		$data = substr($data, 4);
		
		if (strpos($data, ':') !== false){
		
			$datasplit = explode(':', $data);
			
			$title = trim($datasplit[0]);
			$value = trim($datasplit[1]);
		
		} else {
		
			$title = '';
			$value = $data;
		
		}
		
		// Date:
				
		if ($subtype == 'DTE'){
			$this->metadata['date'] = $value;
		}
		
		// Fix accuracy:
				
		if ($subtype == 'FXA'){
			$this->metadata['fix-accuracy'] = (int)substr($value, 0, 3);
		}
		
		// Pilot:
		
		if ($subtype == 'PLT'){
			$this->metadata['pilot'] = $value;
		}
		
		// Glider type:
		
		if ($subtype == 'GTY'){
			$this->metadata['glider-type'] = $value;
		}
		
		// Glider id:
		
		if ($subtype == 'GID'){
			$this->metadata['glider-id'] = $value;
		}
		
		// Geodetic datum:
		
		if ($subtype == 'DTM'){
			$this->metadata['geodetic-datum'] = $value;
		}
		
		// Firmware version:
		
		if ($subtype == 'RFW'){
			$this->metadata['firmware-version'] = $value;
		}
		
		// Hardware version:
		
		if ($subtype == 'RHW'){
			$this->metadata['hardware-version'] = $value;
		}
		
		// Instrument name:
		
		if ($subtype == 'FTY'){
			$this->metadata['instrument-name'] = $value;
		}
		
		// Pressure sensor:
		
		if ($subtype == 'PRS'){
			$this->metadata['pressure-sensor'] = $value;
		}
		
	}
	
	
	// I records: Definition of additions to B-records
	
	private function parseIRecord($data){
		
		$cnt = (int)substr($data, 0, 2);
		$start = (int)substr($data, 2, 2);
		$end = (int)substr($data, 4, 2);
		$code = substr($data, 6, 3);
		
		$this->fixadditions[] = array(
			'code' => $code,
			'start' => $start,
			'end' => $end,
			'cnt' => $cnt
		);
		
	}
	
	
	// B records: Fix records (lat/long/alt etc.)
	
	private function parseBRecord($data, $extracalculations, $filter){
		
		$time = substr($data, 0, 6);
		$lat = substr($data, 6, 8);
		$lng = substr($data, 14, 9);
		$validity = substr($data, 23, 1);
		$pressurealt = (int)substr($data, 24, 5);
		$gpsalt = (int)substr($data, 29, 5);
		
		$altitude = $pressurealt;
		if ($pressurealt == 0){
			$altitude = $gpsalt;
		}
		
		$hours = (int)substr($time,0,2);
		$minutes = (int)substr($time,2,2);
		$seconds = (int)substr($time,4,2);
		
		$timestamp = $seconds + ($minutes * 60) + ($hours * 60 * 60);
		
		if ( (isset($this->lastfix['time']['t']) && $timestamp - $this->lastfix['time']['t'] >= $this->minStepDuration) || !isset($this->lastfix['time']['t']) ){
			
			$fix = array(
				'time' => array(
					'h' => $hours,
					'm' => $minutes,
					's' => $seconds,
					't' => $timestamp
				),
				'lat' => $this->parseBRecordLatitude($lat),
				'lng' => $this->parseBRecordLongitude($lng),
				'altitude' => $altitude,
				'gpsalt' => $gpsalt,
				'pressalt' => $pressurealt,
				'extra' => array()
			);
			
			// Add fix additions:
			
			foreach($this->fixadditions as $fadd){
				$value = substr($data, $fadd['start']-2, $fadd['end'] - $fadd['start'] + 1);
				$fix['extra'][$fadd['code']] = $value;
			}
			
			// Filter out the new fix if needed:
			
			$fixIsOk = true;
			
			if ($filter){
				
				// Filter out outliers
				
				if (!empty($this->lastfix)){
				
					// Vertical variation filter:
				
					$duration = $fix['time']['t'] - $this->lastfix['time']['t'];
					$heightgain = $fix['altitude'] - $this->lastfix['altitude'];
					$vario = 0;
					if ($duration > 0) $vario = $heightgain / $duration;
					if ($vario > $this->config['max-vario'] || $vario < $this->config['min-vario']){
						$fixIsOk = false;
					}
					
					// Horizontal variation filter:
					
					$distance = $this->distance($fix['lat'], $fix['lng'], $this->lastfix['lat'], $this->lastfix['lng'], "K") * 1000;
					$speed = 0;
					if ($duration > 0) $speed = $distance / $duration * 3.6;
					if ($speed > $this->config['max-speed'] || ($duration > 60 && $speed > ($this->config['max-speed']/100*75) ) ){
						$fixIsOk = false;
					}
					
				}
				
				// Filter out fixes pre takeoff
				
				if (!empty($this->lastfix) && $this->nochange){
					
					if ($fix['lat'] != $this->lastfix['lat'] || $fix['lng'] != $this->lastfix['lng'] || $fix['altitude'] != $this->lastfix['altitude']){
						$this->nochange = false;
					} else {
						$fixIsOk = false;
					}
					
				}
				
			}
			
			// Do some extra calculations if needed:
			
			if ($extracalculations && $fixIsOk){
			
				// Get the route:
				
				if (empty($this->route['takeoff'])){
					$this->route['takeoff'] = array(
						'lat' => $fix['lat'],
						'lng' => $fix['lng'],
						'alt' => $fix['altitude'],
						'h' => $fix['time']['h'],
						'm' => $fix['time']['m'],
						's' => $fix['time']['s']
					);
				}
				
				$this->route['landing'] = array(
					'lat' => $fix['lat'],
					'lng' => $fix['lng'],
					'alt' => $fix['altitude'],
					'h' => $fix['time']['h'],
					'm' => $fix['time']['m'],
					's' => $fix['time']['s']
				);
			
				// Height gain:
				
				$fix['heightgain'] = 0;
				if (isset($this->lastfix['altitude'])){
					$fix['heightgain'] = $fix['altitude'] - $this->lastfix['altitude'];
					
					if ($fix['altitude'] > $this->track['maxaltitude']){
						$this->track['maxaltitude'] = $fix['altitude'];
					}
					
					if ($fix['altitude'] < $this->track['minaltitude']){
						$this->track['minaltitude'] = $fix['altitude'];
					}
					
					if ( ($fix['altitude'] - $this->track['minaltitude']) > $this->track['maxaltitudegain'] ){
						$this->track['maxaltitudegain'] = $fix['altitude'] - $this->track['minaltitude'];
					}
					
				}
				
				// Duration:
				
				$fix['duration'] = 0;
				if (isset($this->lastfix['time']['t'])){
					$fix['duration'] = $fix['time']['t'] - $this->lastfix['time']['t'];
					$this->track['duration'] = $this->track['duration'] + $fix['duration'];
				}
				
				// Vario:
				
				$fix['vario'] = 0;
				if ($fix['duration'] != 0){
					
					$fix['vario'] = round($fix['heightgain'] / $fix['duration'],1);
					
					if ($fix['vario'] > $this->track['maxclimb']){
						$this->track['maxclimb'] = $fix['vario'];
					}
					
					if ($fix['vario'] < $this->track['maxsink']){
						$this->track['maxsink'] = $fix['vario'];
					}
					
				}
				
				// Distance & bearing:
				
				$fix['distance'] = 0;
				$fix['bearing'] = 0;
				$fix['turn-angle'] = 0;
				$fix['turn-dir'] = '';
				if (isset($this->lastfix['lat']) && isset($this->lastfix['lng'])){
				
					$fix['distance'] = $this->distance($fix['lat'], $fix['lng'], $this->lastfix['lat'], $this->lastfix['lng'], "K") * 1000;
					if (is_nan($fix['distance'])) $fix['distance'] = 0;
					
					$this->track['distance'] = $this->track['distance'] + $fix['distance'];
					
					$fix['bearing'] = $this->bearing($this->lastfix['lat'], $this->lastfix['lng'], $fix['lat'], $fix['lng']);
					
					$fix['turn-angle'] = $this->abs_bearing_difference($fix['bearing'], $this->lastfix['bearing']);
					
					if ($fix['bearing'] - $this->lastfix['bearing'] == 0){
						$fix['turn-dir'] = '';
					} else if ( $fix['bearing'] > $this->lastfix['bearing'] && $fix['bearing'] - $this->lastfix['bearing'] <= 180 ){
						$fix['turn-dir'] = 'R';
					} else if ( $fix['bearing'] < $this->lastfix['bearing'] && (360 - $this->lastfix['bearing']) + $fix['bearing'] <= 180 ){
						$fix['turn-dir'] = 'R';
					} else {
						$fix['turn-dir'] = 'L';
					}
				}
				
				// Calculate a progress value from distance and heightgain:
				
				$fix['progress'] = 0;
				if ($fix['duration'] > 0){
					$fix['progress'] = ($fix['distance'] + $fix['heightgain']*$this->config['progress-glide-angle']) / $fix['duration'];
				}
				
				// Speed:
				
				$fix['speed'] = 0;
				if ($fix['duration'] > 0){
					
					$fix['speed'] = $fix['distance'] / $fix['duration'] * 3.6;
					
					if ($fix['speed'] > $this->track['maxspeed']){
						$this->track['maxspeed'] = $fix['speed'];
					}
					
				}
				
				// Are we turning or gliding? (get the last 10 seconds and calculate the variation in bearing)
				
				$bearings = array();
				$minheight = 1000000;
				$maxheight = -1000000;
				
				$testfixes = array($fix);
				for ($i=count($this->fixes)-1; $i>=0; $i--){
					$duration = $fix['time']['t'] - $this->fixes[$i]['time']['t'];
					$testfixes[] = $this->fixes[$i];
					if ($duration >= $this->config['min-turning-detection-duration']) break;
				}
				
				foreach($testfixes as $f){
					if ($f['altitude'] > $maxheight) $maxheight = $f['altitude'];
					if ($f['altitude'] < $minheight) $minheight = $f['altitude'];
					$bearings[] = $f['bearing'];
				}
				
				$bearingDeviation = $this->stats_standard_deviation($bearings);
				$maxBearingDifference = $this->bearing_difference($bearings, $this->config['min-turning-bearing-difference']);
				$fix['bearing-deviation'] = $bearingDeviation;
				
				if ($bearingDeviation > $this->config['min-turning-bearing-deviation'] && $maxBearingDifference >= $this->config['min-turning-bearing-difference']){
					$fix['status'] = 't';
				} else {
					$fix['status'] = 'g';
				}
				
			}
			
			// Add fix:
			
			if ($fixIsOk){
				$this->fixes[] = $fix;
				$this->lastfix = $fix;
			}
			
		}
		
	}
	
	
	// B record latitude parser:
	
	private function parseBRecordLatitude($str)
	{	
		$degree = (int)substr($str,0,2);
		$min = substr($str,2,2).'.'.substr($str,4,3);
		$min = (float)$min;
		
		if (substr($str,-1,1) == 'S'){
			return $this->DMStoDEC($degree, $min, 0) * -1;
		} else {
			return $this->DMStoDEC($degree, $min, 0);
		}
	}
	
	
	// B record longitude parser:
	
	private function parseBRecordLongitude($str)
	{	
		$degree = (int)substr($str,0,3);
		$min = substr($str,3,2).'.'.substr($str,5,3);
		$min = (float)$min;
		
		if (substr($str,-1,1) == 'W'){
			return $this->DMStoDEC($degree, $min, 0) * -1;
		} else {
			return $this->DMStoDEC($degree, $min, 0);
		}
	}
	
	
	// DMS (degree, minutes, seconds) to DEC (decimal) coordinate value parser:
	
	private function DMStoDEC($deg, $min, $sec)
	{
	    return round($deg+((($min*60)+($sec))/3600),6);
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
	private function distance($lat1, $lon1, $lat2, $lon2, $unit) {
	
	  $theta = $lon1 - $lon2;
	  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
	  $dist = acos($dist);
	  $dist = rad2deg($dist);
	  $miles = $dist * 60 * 1.1515;
	  $unit = strtoupper($unit);
	
	  if ($unit == "K") {
	    return ($miles * 1.609344);
	  } else if ($unit == "N") {
	    return ($miles * 0.8684);
	  } else {
	    return $miles;
	  }
	}
	
	
	// Bearing methods:
	
	
	private function bearing($lat1, $lon1, $lat2, $lon2){
		return (rad2deg(atan2(sin(deg2rad($lon2) - deg2rad($lon1)) * cos(deg2rad($lat2)), cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2) - deg2rad($lon1)))) + 360) % 360;
	}
	
	private function abs_bearing_difference($a, $b){
	    return 180 - abs(180- abs($a - $b));
    }
	
	
	// Some statistic methods:
	
	
	private function stats_median ($a){
		//variable and initializations
		$the_median = 0.0;
		$index_1 = 0;
		$index_2 = 0;
		
		//sort the array
		sort($a);
		
		//count the number of elements
		$number_of_elements = count($a); 
		
		//determine if odd or even
		$odd = $number_of_elements % 2;
		
		//odd take the middle number
		if ($odd == 1){
			//determine the middle
			$the_index_1 = $number_of_elements / 2;
			
			//cast to integer
			settype($the_index_1, "integer");
			
			//calculate the median 
			$the_median = $a[$the_index_1];
		} else {
			//determine the two middle numbers
			$the_index_1 = $number_of_elements / 2;
			$the_index_2 = $the_index_1 - 1;
			
			//calculate the median 
			$the_median = ($a[$the_index_1] + $a[$the_index_2]) / 2;
		}
		
		return $the_median;
	}
	
	
	private function stats_mean ($a){
		//variable and initializations
		$the_result = 0.0;
		$the_array_sum = array_sum($a); //sum the elements
		$number_of_elements = count($a); //count the number of elements
		
		//calculate the mean
		$the_result = $the_array_sum / $number_of_elements;
		
		//return the value
		return $the_result;
	}
	
	
	private function stats_variance_population ($a){
		//variable and initializations
		$the_variance = 0.0;
		$the_mean = 0.0;
		$the_array_sum = array_sum($a); //sum the elements
		$number_elements = count($a); //count the number of elements
		
		//calculate the mean
		$the_mean = $the_array_sum / $number_elements;
		
		//calculate the variance
		for ($i = 0; $i < $number_elements; $i++){
			//sum the array
			$the_variance = $the_variance + ($a[$i] - $the_mean) * ($a[$i] - $the_mean);
		}
		
		$the_variance = $the_variance / $number_elements;
		
		//return the variance
		return $the_variance;
	}
	
	
	/**
     * This user-land implementation follows the implementation quite strictly;
     * it does not attempt to improve the code or algorithm in any way. It will
     * raise a warning if you have fewer than 2 values in your array, just like
     * the extension does (although as an E_USER_WARNING, not E_WARNING).
     * 
     * @param array $a 
     * @param bool $sample [optional] Defaults to false
     * @return float|bool The standard deviation or false on error.
     */
    private function stats_standard_deviation(array $a, $sample = false) {
        $n = count($a);
        if ($n === 0) {
            trigger_error("The array has zero elements", E_USER_WARNING);
            return false;
        }
        if ($sample && $n === 1) {
            trigger_error("The array has only 1 element", E_USER_WARNING);
            return false;
        }
        $mean = array_sum($a) / $n;
        $carry = 0.0;
        foreach ($a as $val) {
            $d = ((double) $val) - $mean;
            $carry += $d * $d;
        };
        if ($sample) {
           --$n;
        }
        return sqrt($carry / $n);
    }
    
    
    // Calculate the biggest difference in a sample of bearings:
    
    private function bearing_difference(array $samples, $maxdiff = 180){
    	$max = 0;
    	$uniqueSamples = array();
    	
    	foreach($samples as $s){
    		$uniqueSamples[(int)$s] = true;
    	}
    	$uniqueSamples = array_keys($uniqueSamples);
    	
    	foreach($uniqueSamples as $s1){
	    	foreach($uniqueSamples as $s2){
	    		$diff = $this->abs_bearing_difference($s1, $s2);
	    		if ($diff > $max){
	    			$max = $diff;
	    			if ($max >= $maxdiff) break 2;
	    		}
			}
    	}
    	
    	return $max;
	    
    }
    
	
}