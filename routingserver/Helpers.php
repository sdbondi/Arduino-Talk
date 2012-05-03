<?php

class Helpers {
    static function generate_random_string($length, $unique = false) {      
        $result = '';
    
        $possible = "2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ=";
        $maxlength = strlen($possible);
               
        $i = 0; 
                
        while ($i < $length) { 
          $char = substr($possible, mt_rand(0, $maxlength - 1), 1);
            
          if ($unique && strstr($result, $char)) 
            continue;
                        
          $result .= $char;     
          $i++;

        }

        return $result;
    }
    
    static function parse_headers($string) {        
        $lines = explode("\r\n", $string);
        
        $result = array();        
        foreach ($lines as $line) {            
            // First expty line means headers are done
            if (strlen(trim($line)) == 0) 
                break;
                                    
            $parts = explode(': ', $line, 2);
            if (count($parts) < 2)
                continue;
                
            $result[$parts[0]] = $parts[1];            
        }
        
        return $result;
    }
}