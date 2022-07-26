<?php

class InMap_Inreach extends Joe_Class {

	private $request_endpoint = 'https://explore.garmin.com/feed/share/';
	
	private $request_data = [];
	private $cache_id = '';	

	private $request_string = '';
	private $response_string = '';
	
	private $KML = null;
	private $point_count = 0;
	private $FeatureCollection = [];
	
	function __construct($params_in = null) {
		//Set parameters
		$this->parameters = [
			'mapshare_identifier' => null,
			'mapshare_password' => null,
			'mapshare_date_start' => null,
			'mapshare_date_end' => null,									
		];
					
		parent::__construct($params_in);

		$this->setup_request();
		$this->execute_request();
		$this->process_kml();		
		$this->build_geojson();
	}
	
	function execute_request() {
		//Request is setup
		if($this->cache_id) {
			//Cached response	
			$this->response_string = Joe_Cache::get_item($this->cache_id);

			if($this->response_string === false) {
				//Setup call
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->request_string);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				if($auth_password = $this->get_parameter('mapshare_password')) {
					curl_setopt($ch, CURLOPT_USERPWD, ":" . $auth_password);	//No username			
				}

				//Run it
				curl_exec($ch);

				//cURL success?
				if(! curl_errno($ch)) {
					Joe_Helper::debug(curl_getinfo($ch));
				
					$this->response_string = curl_multi_getcontent($ch);

					//MUST BE VALID KML to go into Cache
					if(is_string($this->response_string) && simplexml_load_string($this->response_string)) {
						//Insert into cache
						Joe_Cache::set_item($this->cache_id, $this->response_string, 15);	//Minutes
					}

					curl_close($ch);
				}
			}	
		}
	}

	function setup_request() {
		//Required
		$url_identifier = $this->get_parameter('mapshare_identifier');
				
		if(! $url_identifier) {
			return false;		
		}

		//Start building the request
		$this->request_string = $this->request_endpoint . $url_identifier;

		//Start date
		if($data_start = $this->get_parameter('mapshare_date_start')) {
			$this->request_data['d1'] = $this->get_parameter('mapshare_date_start');
		}

		//End date
		if($data_end = $this->get_parameter('mapshare_date_end')) {
			$this->request_data['d2'] = $this->get_parameter('mapshare_date_end');
		}
		
		//Append data
		if(sizeof($this->request_data)) {
			$this->request_string .= '?';
			$this->request_string .= http_build_query($this->request_data);
		}	

		//Determine cache ID
		$this->cache_id = Joe_Helper::slug_prefix(md5($this->request_string));
		
		return true;
	}	

	function get_geojson($response_type = 'string') {
		if($response_type == 'string') {
			return json_encode($this->FeatureCollection);
		}
		
		return $this->FeatureCollection;		
	}

	function process_kml() {
		//Do we have a response?
		if($this->response_string) {		
			$this->KML = simplexml_load_string($this->response_string);
		}	

		$this->update_point_count();					
	}
	
	function build_geojson() {
		$this->FeatureCollection = [
			'type' => 'FeatureCollection',
			'features' => []
		];
		
		//We have Points
		if($this->point_count) {
			//Each Placemark
			for($i = 0; $i < sizeof($this->KML->Document->Folder->Placemark); $i++) {
				$Placemark = $this->KML->Document->Folder->Placemark[$i];
				
				//Create Feature
				$Feature = [
					'type' => 'Feature',
					'properties' => [
						'className' => 'inmap-info-item'
					],
					'geometry' => []
				];

				// =========== Point ===========
				
				if($Placemark->Point->coordinates) {
					$icon_class = 'inmap-icon';
					$time_ago = Joe_Helper::time_ago(strtotime($Placemark->TimeStamp->when));
				
					//Coordinates
					$coordinates = explode(',', (String)$Placemark->Point->coordinates);																

					//Invalid
					if(sizeof($coordinates) < 2 || sizeof($coordinates) > 3) {
						continue;						
					}
					
					$Feature['geometry']['type'] = 'Point';
					$Feature['geometry']['coordinates'] = $coordinates;

					//Title
					$title = '[' . ($i + 1) . '/' . $this->point_count . ']';
					if(isset($Placemark->TimeStamp->when)) {
						$title .= $time_ago;						
					}
					$Feature['properties']['title'] = $title;
					
					//Classes
					//First (oldest)
					if($i === 0) {
						$Feature['properties']['className'] .= ' inmap-first';

						//*Only* single item
						if($this->point_count === 1) {
							$Feature['properties']['className'] .= ' inmap-last inmap-active inmap-only';
						}


						//Most recent
						$Feature['properties']['title'] = '[' . __('First', Joe_Config::get_item('plugin_text_domain')) . ']';
						$Feature['properties']['title'] .= $time_ago;	
					//Last - *LATEST*
					} elseif(
						//EOF array
						$i === sizeof($this->KML->Document->Folder->Placemark) - 2
					) {
						//Active
						$Feature['properties']['className'] .= ' inmap-last inmap-active';

						//Most recent
						$Feature['properties']['title'] = '[' . __('Latest', Joe_Config::get_item('plugin_text_domain')) . ']';
						$Feature['properties']['title'] .= $time_ago;						
					}					

					//Extended Data?
					if(isset($Placemark->ExtendedData)) {
						if(sizeof($Placemark->ExtendedData->Data)) {
							$extended_data = [];
						
							//Each
							for($j = 0; $j < sizeof($Placemark->ExtendedData->Data); $j++) {
								$key = (string)$Placemark->ExtendedData->Data[$j]->attributes()->name;
							
								//Must be a key we are interested in
								if(in_array($key, Joe_Config::get_item('kml_data_include'))) {
									$value = (string)$Placemark->ExtendedData->Data[$j]->value;

									//By Key
									switch($key) {
										case 'Id' :
											$Feature['properties']['id'] = $value;

											$extended_data[$key] = $value;																

											break;
										case 'Id' :
											$Feature['properties']['id'] = $value;

											$extended_data[$key] = $value;																

											break;
										case 'Text' :
											//Skip empty text
											if(! empty($value)) {
												$extended_data[$key] = $value;																
											}

											break;
										default :
											$extended_data[$key] = $value;																

											break;
									}
								}								
							}						
						}
					}
					
					//Valid GPS
					if(isset($extended_data['Valid GPS Fix']) && 'True' === $extended_data['Valid GPS Fix']) {
						$icon_class .= ' inmap-icon-gps';
					}
					
					//By event
					if(isset($extended_data['Event'])) {
						//Remove periods!
						$extended_data['Event'] = trim($extended_data['Event'], '.');

						switch($extended_data['Event']) {
							case 'Tracking turned on from device' :
							case 'Tracking turned off from device' :
							case 'Tracking interval received' :
							case 'Tracking message received' :

								break;
							case 'Msg to shared map received' :
								$icon_class .= ' inmap-icon-message inmap-icon-custom';
			
								break;
							case 'Quick Text to MapShare received' :
								$icon_class .= ' inmap-icon-message inmap-icon-quick';
								
								break;
// 							default :
//  								Joe_Helper::debug($extended_data);
// 							
// 								break;									
						}

						//Icon
						$Feature['properties']['icon'] = [
							'className' => 'inmap-marker',
							'iconSize' => [ 15, 15 ],
							'html' => '<div class="' . $icon_class . '"></div>'
						];

						//Description
						$description = '<div class="inmap-info-desc">';
						$description .= '<div class="inmap-info-title">' . $Feature['properties']['title'] . '</div>';
					
						//We have data														
						if(sizeof($extended_data)) {
							$description .= Joe_Helper::assoc_array_table($extended_data);
	
							$description .= '<div class="inmap-info-expand">' . __('More detail +', Joe_Config::get_item('plugin_text_domain'))  . '</div>';
						}
						$description .= '</div>';

						$Feature['properties']['description'] = $description;																
					}

				// =========== LineString ===========
				
				} elseif($Placemark->LineString->coordinates) {
					$coordinates = (string)$Placemark->LineString->coordinates;
					$coordinates = preg_split('/\r\n|\r|\n/', $coordinates);
					
					//Valid array
					if(sizeof($coordinates)) {

						$Feature['geometry']['type'] = 'LineString';

						//Each Coordinate
						foreach($coordinates as $point) {
							$coords = explode(',', $point);													
					
							//Invalid
							if(sizeof($coords) < 2 || sizeof($coords) > 3) {
								continue;						
							}	

							$Feature['geometry']['coordinates'][] = $coords;
						}
					}
				}
				
				//Style
				$Feature['properties']['style']['weight'] = 2;
				$Feature['properties']['style']['color'] = Joe_Config::get_setting('appearance', 'colours', 'tracking_colour');
				
				$this->FeatureCollection['features'][] = $Feature;
			}
			
			//Reverse order (most recent first)
			$this->FeatureCollection['features'] = array_reverse($this->FeatureCollection['features']);
		//No points in KML
		} else {

		}
	}
	
	function update_point_count() {
		$this->point_count = 0;
		
		if(
 			is_object($this->KML)
 			&& isset($this->KML->Document->Folder->Placemark)
 			&& is_iterable($this->KML->Document->Folder->Placemark)	
		) {
			foreach($this->KML->Document->Folder->Placemark as $Placemark) {
				if($Placemark->Point->coordinates) {
					$this->point_count++;
				}			
			}
		}
		
		return $this->point_count;
	}
}