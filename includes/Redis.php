<?php
declare(strict_types = 1);

namespace Mireiawen\Reader;

/**
 * Redis caching backend wrapper implementation
 *
 * @package Mireiawen\Reader
 */
class Redis
{
	/**
	 * The timeout for persistent data storing
	 *
	 * @var int|null
	 */
	public const PERSISTENT = NULL;
	
	/**
	 * The Redis cache backend
	 *
	 * @var \Redis
	 */
	protected $redis;
	
	/**
	 * Initialize the Redis connection and return the connected of Redis instance
	 *
	 * @param string $hostname
	 * @param int|null $port
	 * @param string|null $password
	 * @param int|null $database
	 *
	 * @return Redis
	 * @throws \Exception
	 */
	public static function CreateConnection(string $hostname = 'localhost', ?int $port = NULL, ?string $password = NULL, ?int $database = NULL) : Redis
	{
		if (!\extension_loaded('redis'))
		{
			throw new \Exception(\_('Redis extension is required!'));
		}
		
		/**
		 * @var \Redis
		 */
		$redis = new \Redis();
		
		// Connect to the default port, or possibly socket
		if ($port === NULL)
		{
			if ($redis->pconnect($hostname) === FALSE)
			{
				throw new \Exception(\sprintf(\_('Unable to connect to "%s"'), $hostname));
			}
		}
		
		// Connect with the port
		else
		{
			if ($redis->pconnect($hostname, $port) === FALSE)
			{
				throw new \Exception(\sprintf(\_('Unable to connect to "%s:%d"'), $hostname, $port));
			}
		}
		
		if ($password !== NULL)
		{
			// Authenticate us to the Redis
			if ($redis->auth($password) === FALSE)
			{
				throw new \Exception(\sprintf(\_('Unable to connect to "%s": %s'), $hostname, \_('Authentication failure')));
			}
		}
		
		if ($database !== NULL)
		{
			// Select the cache database
			if ($redis->select($database) === FALSE)
			{
				throw new \Exception(\sprintf(\_('Unable to connect to "%s": %s'), $hostname, \_('Unable to select the cache database')));
			}
		}
		
		// Create the instance
		return new self($redis);
	}
	
	/**
	 * Initialize the Redis connection
	 *
	 * @param \Redis $redis
	 *
	 */
	public function __construct(\Redis $redis)
	{
		$this->redis = $redis;
		
		// Make sure the connection is up, should throw RedisException on failure
		$this->redis->ping();
	}
	
	/**
	 * Fetch the given key value from the cache backend
	 *
	 * @param string $key
	 *    The key to fetch the data for
	 *
	 * @return mixed
	 *    The data for the given key
	 *
	 * @throws \Exception on errors
	 */
	public function Fetch(string $key)
	{
		// Check if the key exists
		if ($this->redis->exists($key) === FALSE)
		{
			throw new \Exception(\sprintf(\_('The key %s was not found'), $key));
		}
		
		return \unserialize($this->redis->get($key));
	}
	
	/**
	 * Store the key to the cache
	 *
	 * @param string $key
	 *    The key to store the data to
	 * @param mixed $value
	 *    The key value
	 * @param int $ttl
	 *    The time the key should live in the cache,
	 *    self::PERSISTENT to not timeout
	 *
	 * @throws \Exception
	 */
	public function Store(string $key, $value, ?int $ttl = NULL) : void
	{
		if ($ttl !== NULL)
		{
			$return = $this->redis->setex($key, $ttl, \serialize($value));
		}
		else
		{
			$return = $this->redis->set($key, \serialize($value));
		}
		
		if ($return === FALSE)
		{
			throw new \Exception(\sprintf(\_('Redis failure:')));
		}
	}
	
	/**
	 * Check if the value exists in the caching backend
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function Exists(string $key) : bool
	{
		return (bool)$this->redis->exists($key);
	}
	
	/**
	 * Flush the key value out from the cache
	 *
	 * @param string $key
	 *    The key to flush
	 */
	public function Flush(string $key) : void
	{
		$this->redis->delete($key);
	}
	
	/**
	 * List all the keys in the cache
	 *
	 * @retval array
	 *    An array of key name strings
	 */
	public function Keys() : array
	{
		return $this->redis->keys('*');
	}
}
