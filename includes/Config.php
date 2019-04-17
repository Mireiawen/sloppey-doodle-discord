<?php

declare(strict_types = 1);

namespace Reader;

class Config
{
	/**
	 * @var array
	 */
	protected $data;
	
	public function __construct(string $filename)
	{
		if (!is_readable($filename))
		{
			throw new \Exception(sprintf(_('Unable to read the file %s'), $filename));
		}
		
		$this->data = json_decode(file_get_contents($filename), TRUE, 512, JSON_THROW_ON_ERROR);
	}
	
	/**
	 * Magic method to have GetX methods to work with less work than writing each one manually
	 *
	 * @param string $name
	 * @param array $params
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __call(string $name, array $params)
	{
		if (\preg_match('/^Get(?P<name>.*)$/', $name, $matches) === FALSE)
		{
			throw new \Exception(\sprintf(\_('Invalid call to %s'), $name));
		}
		
		if (!isset($matches['name']))
		{
			throw new \Exception(\sprintf(\_('Invalid call to %s'), $name));
		}
		
		if (!empty($params))
		{
			throw new \Exception(\sprintf(\_('Invalid amount of parameters for %s'), $name));
		}
		
		return $this->__get($matches['name']);
	}
	
	/**
	 * Magic method to get values from data array
	 *
	 * @param string $key
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get(string $key)
	{
		if (!$this->__isset($key))
		{
			throw new \Exception(\sprintf(\_('The key %s is not available'), $key));
		}
		
		return $this->data[$key];
	}
	
	/**
	 * Magic method to check if value exists
	 *
	 * @param $name
	 *
	 * @return bool
	 */
	public function __isset(string $name) : bool
	{
		return \array_key_exists($name, $this->data);
	}
}
