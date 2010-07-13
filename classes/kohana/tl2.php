<?php defined('SYSPATH') or exit();

/**
 * This file is part of TL2.
 *
 * Copyright (c) 2010, Deoxxa Development
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package kohana-tl2
 */

/**
 * TL2 main class
 *
 * @package kohana-tl2
 * @author MasterCJ <mastercj@mastercj.net>
 * @version 0.1
 * @license http://mastercj.net/license.txt
 */
class Kohana_TL2 {
	/**
	 * @var string The location of the language files
	 */
	protected static $location = null;
	/**
	 * @var string The default language(s)
	 */
	protected static $language = null;
	/**
	 * @var array Cached language resources
	 */
	protected static $languages = array();
	/**
	 * @var string The configuration profile name
	 */
	protected static $profile = 'default';
	/**
	 * @var boolean Whether or not to return debugging information
	 */
	protected static $debug = false;
	/**
	 * @var array A list of profiles that have been initialised
	 */
	protected static $init = array();
	/**
	 * @var string A cache profile to use for storing language resources
	 */
	protected static $cache = null;

	/**
	 * Constructor
	 *
	 * @param string The profile name, corresponds to the config file
	 * @return boolean An indicator of whether or not the profile was loaded
	 */
	public static function setup($profile) {
		$config = Kohana::config('tl2.'.$profile);
		self::$profile = $profile;

		if (is_array($config) && isset($config['location']) && is_string($config['location'])) {
			self::$location = $config['location'];
		}

		if (is_array($config) && isset($config['default_language']) && is_string($config['default_language'])) {
			self::$language = $config['default_language'];
		}

		if (is_array($config) && isset($config['debug']) && ($config['debug'] == true)) {
			self::$debug = true;
		} else {
			self::$debug = false;
		}

		if (is_array($config) && isset($config['cache']) && is_string($config['cache'])) {
			self::$cache = $config['cache'];
		} else {
			self::$cache = null;
		}

		self::$init[$profile] = true;

		return true;
	}

	/**
	 * load a language file
	 *
	 * @param string The language code
	 * @return boolean An indicator of whether or not the language was loaded
	 */
	protected static function load($language) {
		// Check if it's already loaded
		if (isset(self::$languages[$language])) { return true; }
		// Try to get it from the cache
		if (self::$cache !== null) {
			$cache_key = sprintf('tl2.%s.%s', self::$profile, $language);
			if ($data = Cache::instance(self::$cache)->get($cache_key)) {
				self::$languages[$language] = $data;
				return true;
			}
		}
		// Get the file...
		if (!$file = Kohana::find_file(self::$location, $language, 'json')) { return false; }
		// ...load it...
		if (!$json = file_get_contents($file)) { return false; }
		// ...parse it
		if (!$data = json_decode($json, true)) { return false; }
		// Put it in the cache
		if (self::$cache !== null) {
			$cache_key = sprintf('tl2.%s.%s', self::$profile, $language);
			Cache::instance(self::$cache)->set($cache_key, $data, 3600);
		}
		// Put the data in
		self::$languages[$language] = $data;
		return true;
	}

	/**
	 * Get a translated string
	 *
	 * @param string The resource key
	 * @param string A list of languages from most to least preferred
	 * @return string|false The string, or false on error
	 */
	protected static function get_string($key, $languages=null) {
		// Default the languages string if necessary
		if ($languages === null) { $languages = self::$language; }

		// Validate key and languages strings
		if (!is_string($key)       || !strlen($key))       { return false; }
		if (!is_string($languages) || !strlen($languages)) { return false; }

		foreach (explode(',', $languages) as $language) {
			// Try to get it from the cache
			if (self::$cache !== null) {
				$cache_key = sprintf('tl2.%s.%s.%s', self::$profile, $language, $key);
				if ($str = Cache::instance(self::$cache)->get($cache_key)) { return $str; }
			}

			// Load language if necessary, if impossible, skip
			if (!isset(self::$languages[$language]) && !self::load($language)) { continue; }

			// Store reference to the language we want
			$arr = &self::$languages[$language];

			// DORILLU down
			foreach (explode(':', $key) as $seg) {
				// Wuh-oh can't find this key
				if (!isset($arr[$seg])) { break; }
				$arr = &$arr[$seg];
			}

			// We're at the right place, return
			// ...first put it in the cache though
			if (self::$cache !== null) {
				$cache_key = sprintf('tl2.%s.%s.%s', self::$profile, $language, $key);
				Cache::instance(self::$cache)->set($cache_key, $arr, 3600);
			}
			if (is_string($arr)) { return $arr; }
		}

		if (self::$debug) {
			return sprintf('/!\\ UNTRANSLATED KEY: "%s" /!\\', $key);
		} else {
			return false;
		}
	}

	/**
	 * Get an array of translated strings
	 * Mostly used for the tn() function
	 *
	 * @param string The resource key
	 * @param string A list of languages from most to least preferred
	 * @return array|false The strings, or false on error
	 */
	protected static function get_array($key, $languages=null) {
		// Default the languages string if necessary
		if ($languages === null) { $languages = self::$language; }

		// Validate key and languages strings
		if (!is_string($key)       || !strlen($key))       { return false; }
		if (!is_string($languages) || !strlen($languages)) { return false; }

		foreach (explode(',', $languages) as $language) {
			// Try to get it from the cache
			if (self::$cache !== null) {
				$cache_key = sprintf('tl2.%s.%s.%s', self::$profile, $language, $key);
				if ($arr = Cache::instance(self::$cache)->get($cache_key)) { return $arr; }
			}

			// Load language if necessary, if impossible, skip
			if (!isset(self::$languages[$language]) && !self::load($language)) { continue; }

			// Store reference to the language we want
			$arr = &self::$languages[$language];

			// DORILLU down
			foreach (explode(':', $key) as $seg) {
				// Wuh-oh can't find this key
				if (!isset($arr[$seg])) { $arr = false; break; }
				$arr = &$arr[$seg];
			}

			// We're at the right place, return
			// Cache magic makes your children happy
			if ((self::$cache !== null) && is_array($arr)) {
				$cache_key = sprintf('tl2.%s.%s.%s', self::$profile, $language, $key);
				Cache::instance(self::$cache)->set($cache_key, $arr, 3600);
			}
			if (is_array($arr)) { return $arr; }
		}

		if (self::$debug) {
			return array(0 => sprintf('/!\\ UNTRANSLATED KEY: "%s" /!\\', $key));
		} else {
			return false;
		}
	}

	/**
	 * Get a translated string with any variabled in it replaced
	 *
	 * @param string The resource key
	 * @param array An array of key => value replacements for variables
	 * @param string A list of languages from most to least preferred
	 * @return string|false The string, or false on error
	 */
	public static function tr($key, $args=null, $languages=null) {
		if ((!isset(self::$init[self::$profile]) || !self::$init[self::$profile]) && !self::setup(self::$profile)) { return false; }

		// Validate the key
		if (!is_string($key) || !strlen($key)) { return false; }

		// Set the default language if it's not set already
		if ($languages === null) { $languages = self::$language; }

		// Try to get the string
		if (!$str = self::get_string($key, $languages)) { return false; }

		// Process the replacements
		if (is_array($args) && count($args)) {
			$args = array_combine(preg_replace('#^(.*)$#', '%\1%', array_keys($args)), array_values($args));
			$str = str_replace(array_keys($args), array_values($args), $str);
		}

		// All done!
		return $str;
	}

	/**
	 * Get a valid plural form of a string
	 *
	 * @param string The resource key
	 * @param integer The number for the plural
	 * @param string A list of languages from most to least preferred
	 * @return string|false The string, or false on error
	 */
	public static function tn($key, $number=0, $languages=null) {
		if ((!isset(self::$init[self::$profile]) || !self::$init[self::$profile]) && !self::setup(self::$profile)) { return false; }

		// Validate the key
		if (!is_string($key) || !strlen($key)) { return false; }

		// Validate the number
		if (!is_integer($number)) { return false; }

		// Set the default language if it's not set already
		if ($languages === null) { $languages = self::$language; }

		// Try to get the array
		if (!$arr = self::get_array($key, $languages)) { return false; }

		// Get the closest key that's less than the number specified
		$closest = null;
		$keys = array_keys($arr);
		foreach ($keys as $n) {
			if ($closest === null) { $closest = $n; }
			if ($n > $number) { break; }
			$closest = $n;
		}

		// Replace the number in the string
		$str = str_replace('%number%', $number, $arr[$closest]);

		// All done!
		return $str;
	}
}

?>