<?php
declare(strict_types = 1);

namespace Mireiawen\Reader;

/**
 * Configuration helper class
 *
 * @package Mireiawen\Reader
 */
class Config
{
	/**
	 * Configuration values themselves
	 *
	 * @var array
	 */
	protected $data;
	
	/**
	 * Config constructor.
	 *
	 * @param string $filename
	 *    The JSON file name to read
	 *
	 * @throws \Exception
	 *    On errors
	 */
	public function __construct(string $filename)
	{
		if (!\extension_loaded('json'))
		{
			throw new \Exception(\sprintf(\_('Extension %s is required'), 'json'));
		}
		
		if (!is_readable($filename))
		{
			throw new \Exception(\sprintf(\_('Unable to read the file %s'), $filename));
		}
		
		$this->data = \json_decode(\file_get_contents($filename), TRUE, 512, JSON_THROW_ON_ERROR);
	}
	
	/**
	 * Get the configuration variable and return its value, or default if it is not set
	 *
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return mixed
	 *
	 * @throws \Exception
	 */
	public function Get(string $key, $default = NULL)
	{
		if (isset($this->data[$key]))
		{
			return $this->data[$key];
		}
		
		if ($default === NULL)
		{
			throw new \Exception(\sprintf(\_('The configuration key %s was requested but was not found and no default value provided'), $key));
		}
		
		return $default;
	}
}
