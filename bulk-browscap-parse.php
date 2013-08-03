<?php

/**
 * @author Mikołaj Misiurewicz <quentin389+bpfb@gmail.com>
 * 
 * @link https://github.com/quentin389/bulk-parser-for-browscap
 * 
 * @version 1.0
 *
 */

BulkParserForBrowscap::parse();

class BulkParserForBrowscap
{
  const MIN_WEIGHT_IN_FILE = 1;
  const MAX_WEIGHT_IN_FILE = 10000;
  
  const KEEP_DECIMALS = 2;
  
  protected static $main_folder;
  protected static $imports_folder = 'imports/';
  protected static $main_ua_file = 'user-agents-to-parse.txt';
  protected static $parsed_files_file = 'already-parsed.txt';
  
  protected static $min_weight;
  protected static $max_weight;
  
  protected static $parsed_files = array();
  
  protected static $parsed_uas = array();
  
  public static function parse()
  {
    self::extract();
    
    self::loadExistingData();
    
    $something_parsed = false;
    
    foreach (glob(self::$imports_folder . '*.txt') as $one_file)
    {
      $something_parsed |= self::parseFile($one_file);
    }
    
    if (!$something_parsed)
    {
      echo "no files parsed\n";
    }
    else
    {
      self::verify();
      
      self::saveNewData();
    }
    
    echo "--- the end ---\n";
  }
  
  protected static function extract()
  {
    echo "extracting\n";
    
    self::$main_folder = dirname(__FILE__) . '/';
    self::$imports_folder = self::$main_folder . self::$imports_folder;
    self::$main_ua_file = self::$main_folder . self::$main_ua_file;
    self::$parsed_files_file = self::$main_folder . self::$parsed_files_file;
    
    passthru('cd ' . self::$imports_folder . ' && for i in `ls *.tar.gz 2>/dev/null`; do tar xvfz "$i"; done', $error_code);
    
    if ($error_code) throw new Exception('extracting files failed');
  }
  
  protected static function loadExistingData()
  {
    if (file_exists(self::$parsed_files_file))
    {
      self::$parsed_files = self::explodeByLine(self::$parsed_files_file);
    }
    
    if (file_exists(self::$main_ua_file))
    {
      $data = self::explodeByLine(self::$main_ua_file);
      
      try
      {
        foreach ($data as $line_no => $one_line)
        {
          list($weight, $user_agent) = self::parseLine($one_line, $line_no);
          
          self::$parsed_uas[$user_agent] = $weight;
        }
      }
      catch (Exception $e)
      {
        throw new Exception('ERROR: main file ' . self::$main_ua_file . ' has incorrect format - ' . $e->getMessage());
      }
    }
    
    echo "loaded existing data\n";
  }
  
  protected static function saveNewData()
  {
    file_put_contents(self::$parsed_files_file, implode("\n", self::$parsed_files));
    
    $user_agents_to_save = array();
    
    foreach (self::$parsed_uas as $user_agent => $weight)
    {
      $user_agents_to_save[] = number_format($weight, self::KEEP_DECIMALS, '.', '') . "\t" . $user_agent;
    }
    
    if ($user_agents_to_save)
    {
      file_put_contents(self::$main_ua_file, implode("\n", $user_agents_to_save));
    }
    
    echo "saved new data\n";
  }
  
  protected static function parseFile($one_file)
  {
    $file_name = basename($one_file);
    
    if (in_array($file_name, self::$parsed_files))
    {
      echo "file $file_name was already parsed\n";
      return false;
    }
    
    $data = self::explodeByLine($one_file);
    
    try
    {
      list($max_weight, ) = self::parseLine(reset($data), 'first');
      list($min_weight, ) = self::parseLine(end($data), 'last');
    }
    catch (Exception $e)
    {
      echo "ERROR: file $one_file skipped: " . $e->getMessage() . "\n";
      return false;
    }
    
    $weight_normalizer = (self::MAX_WEIGHT_IN_FILE - self::MIN_WEIGHT_IN_FILE) / ($max_weight - $min_weight);
    
    foreach ($data as $line_no => $one_line)
    {
      try
      {
        list($current_weight, $user_agent) = self::parseLine($one_line, $line_no);
        
        $normalized_weight = ($current_weight - $min_weight) * $weight_normalizer + self::MIN_WEIGHT_IN_FILE;
        
        @self::$parsed_uas[$user_agent] += $normalized_weight;
      }
      catch (Exception $e)
      {
        echo "ERROR in $one_file: " . $e->getMessage() . "\n";
      }
    }
    
    self::$parsed_files[] = $file_name;
    
    echo 'parsed ' . basename($one_file) . "\n";
    
    return true;
  }
  
  protected static function parseLine($line, $line_no)
  {
    $line = explode("\t", $line, 2);
    
    if (!isset($line[1]) || !is_numeric($line[0]))
    {
      throw new Exception('line parsing failed (' . $line_no . ')');
    }
    
    return $line;
  }
  
  protected static function verify()
  {
    $time = microtime(true);
    
    echo "verifying\n";
    
    $found = 0;
    $not_found = 0;
    
    foreach (self::$parsed_uas as $user_agent => $weight)
    {
      if (empty($user_agent))
      {
        // completely ignore empty user agent string
        continue;
      }
      
      $browscap_info = get_browser($user_agent);
      
      if (empty($browscap_info->browser))
      {
        throw new Exception("Something is wrong with browscap.");
      }
      
      if ('Default Browser' != $browscap_info->browser)
      {
        unset(self::$parsed_uas[$user_agent]);
        
        echo '.';
        $found++;
      }
      else
      {
        echo '!';
        $not_found++;
      }
    }
    
    arsort(self::$parsed_uas, SORT_NUMERIC);
    
    echo "\nverified " . ($found + $not_found) . ' entries in ' . number_format(microtime(true) - $time, 0) . ' seconds; '
      . number_format($found) . ' found in browscap, ' . number_format($not_found) . " not found\n";
  }
  
  protected static function explodeByLine($file_name)
  {
    $data = trim(file_get_contents($file_name));
    
    $data = str_replace("\r\n", "\n", $data);
    
    $data = str_replace("\r", "\n", $data);
    
    return explode("\n", $data);
  }
}

