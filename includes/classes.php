<?php
/*
 *  Ergaenzung der Funktion "array_column()" für PHP Versionen < 5.5.0
 *  Ist dies notwendig ?  
 *  Wird hier benutzt, um Datensaetze nach bestimmer ID zu filtern. 
 *
 */
if (! function_exists('array_column')) {
    function array_column(array $input, $columnKey, $indexKey = null) {
        $array = array();
        foreach ($input as $value) {
            if ( ! isset($value[$columnKey])) {
                trigger_error("Key \"$columnKey\" does not exist in array");
                return false;
            }
            if (is_null($indexKey)) {
                $array[] = $value[$columnKey];
            }
            else {
                if ( ! isset($value[$indexKey])) {
                    trigger_error("Key \"$indexKey\" does not exist in array");
                    return false;
                }
                if ( ! is_scalar($value[$indexKey])) {
                    trigger_error("Key \"$indexKey\" does not contain scalar value");
                    return false;
                }
                $array[$value[$indexKey]] = $value[$columnKey];
            }
        }
        return $array;
    }
}


/*
 *	Bauplan fuer alle Kindklassen dieses Plugins.
 *	Moeglicherweise unnoetig, 
 *	u.U werden jedoch noch weitere Objekttypen 
 *  in zukuenftigen Versionen unterstuetzt.
 */
abstract class Json_Data {
	protected $json_urls = NULL;
	protected $format = NULL;
	
	abstract protected function get_data();

	abstract protected function create_html($data, $ressources);
	
}



class Studentische_Arbeiten extends Json_Data {
    
    private $id = NULL;
    private $task = NULL;
    private $advisor = NULL;
    private $status = NULL;
    
    function __construct($json_urls, $id, $task, $format, $advisor, $status) {
        $this->id = $id;
        $this->json_urls = $json_urls;
		$this->task = $task;
		$this->format = $format;
		$this->advisor = $advisor;
		$this->status = $status;
    }
    /*
	 *	Erstellt Array aus der JSON-Zeichenkette
	 *	@return [mixed] array
	 */
    public function get_data() {
        
        if (!empty($this->json_urls)) {
            $data = json_decode(wp_remote_retrieve_body(wp_safe_remote_get($this->json_urls)), true);
            // Falls die entfernte JSON-Quelle kein (gueltiges) Array zurueckliefert,
            // dann ist keine Verarbeitung moeglich.
            if (!is_array($data)) {
                $data = '';
            }
            //print_r($data);
        } else {
            echo "Keine URL";
            return; 
        }          
        
		// Falls ID als Parameter uebergeben wurde, filtere nach dieser.
		if ($this->id != '' && is_array($data)) {			
			$key = array_search($this->id, array_column($data, 'id'));
            if ($key === false) {
                $data = "";
            } else {
				// Unsauber. Das Template File nimmt nur numerische Arrays entgegen.
				// Deshalb hier diese unschoene Umwandlung.
				$tmp = $data[$key];			
				$data = array();
				$data[0] = $tmp;
				unset($tmp);
			}
		}    
		
		// Falls Advisor als Parameter uebergeben wurde, filtere nach diesem Betreuer.
		if ($this->advisor != '' && is_array($data)) {
			$tmp = $data;
			$data = array();
			$i = 0;
			foreach($tmp as $array) {
				if (isset($array['betreuer']) && stripos($array['betreuer'], $this->advisor) !== false)	{
					$data[$i] = $array;
					$i++;
				}				
			}
			if (!$data) {
				echo "";	
				return;
			}
		}    
		
	     // Falls Status als Parameter uebergeben wurde, filtere nach diesem Vergabestatus.
		if ($this->status != '' && is_array($data)) {
			$tmp = $data;
			$data = array();
			$i = 0;            
			foreach($tmp as $array) {                
				if (isset($array['status']) && $this->status == substr($array['status'], 0, strlen($array['status'])))	{
                    
					$data[$i] = $array;
					$i++;
				}				
			}
			if (!$data) {
				echo "";	
				return;
			}
		}    
		
        return $data;        
    }
	
	/*
	 *   Erstellt die HTML Ausgabe
	 *	 @return string
	 */
	public function create_html($data, $ressources) {
		$output = '';
		// Faengt Fehler ab, falls unerwartete Dinge als ID uebergeben wurden
		if ( !is_array($data) ) {
			$output = "";	
		}
		elseif (count(array_filter($data)) == 0) {
			$output = '';
		} else {
			// Baut entsprechend des optionalen Format Parameters die HTML Ausgabe zusammen
			//$output .= '<div><h2>' . ucfirst($this->task) . '</h2>';
			switch($this->format) {
				case 'accordion': 
					$output .= build_wp_accordion($data, 0, $ressources);
					break;
				default: 
					$output .= build_html_list($data, $ressources);
					break;	
			}
			$output .= '</div>';
		}
		return $output;
	}
}






class Studentische_Arbeiten_Alle extends Json_Data {           
	
	function __construct($json_urls, $format) {
		$this->json_urls = $json_urls;
		$this->format = $format;
	}
		
    function get_data() {
		if (count($this->json_urls) > 1) {    
			$data = array();
			foreach ($this->json_urls as $key => $url) {
				$body = json_decode(wp_remote_retrieve_body(wp_safe_remote_get($url)), true);
				// Nur tatsaechlich vorhandene Daten uebernehmen, damit count(array_filter()) korrekt arbeitet.
				$data[$key] = is_array($body) ? $body : '';
			}
		} else {
			$stud_arbeit = new Studentische_Arbeiten($this->json_urls, '', '', '', '', '');
			$data = $stud_arbeit->get_data();
		}
		//print_r($data);
		return $data;
	}
	
	function create_html($data, $ressources) {
		$output = '';
		$accordion_count = 0;
		
		if ( !is_array($data) || count(array_filter($data)) == 0) {
			$output = '';
		} else {					
			foreach ($data as $heading => $arr) {
				// Ueberspringt Quellen, die keine (gueltigen) Daten geliefert haben.
				if (!is_array($arr)) {
					continue;
				}
				//$output .= '<div><h2>' . ucfirst($heading) . '</h2>';
				switch($this->format) {
					case 'accordion': 						
						$output .= build_wp_accordion($arr, $accordion_count, $ressources);
						$accordion_count++;
						break;
					default: 
						$output .= build_html_list($arr, $ressources);
						break;						
				}
				$output .= '</div>';
			}			
		}
		return $output;
	}
	
}
