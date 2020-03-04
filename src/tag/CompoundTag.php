<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\nbt\tag;

use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\NbtStreamReader;
use pocketmine\nbt\NbtStreamWriter;
use pocketmine\nbt\NoSuchTagException;
use pocketmine\nbt\ReaderTracker;
use pocketmine\nbt\UnexpectedTagTypeException;
use function assert;
use function count;
use function current;
use function func_num_args;
use function get_class;
use function gettype;
use function is_a;
use function is_int;
use function is_object;
use function key;
use function next;
use function reset;
use function str_repeat;

final class CompoundTag extends Tag implements \ArrayAccess, \Iterator, \Countable{
	use NoDynamicFieldsTrait;

	/** @var Tag[] */
	private $value = [];

	public function __construct(){
		self::restrictArgCount(__METHOD__, func_num_args(), 0);
	}

	/**
	 * Helper method for easier fluent usage.
	 * @return CompoundTag
	 */
	public static function create() : self{
		return new self;
	}

	/**
	 * @return int
	 */
	public function count() : int{
		return count($this->value);
	}

	/**
	 * @return int
	 */
	public function getCount(){
		return count($this->value);
	}

	/**
	 * @return Tag[]
	 */
	public function getValue(){
		return $this->value;
	}

	/*
	 * Here follows many functions of misery for the sake of type safety. We really needs generics in PHP :(
	 */

	/**
	 * Returns the tag with the specified name, or null if it does not exist.
	 *
	 * @phpstan-template T of Tag
	 *
	 * @param string $name
	 * @param string $expectedClass Class that extends Tag
	 * @phpstan-param class-string<T> $expectedClass
	 *
	 * @return Tag|null
	 * @phpstan-return T|null
	 * @throws UnexpectedTagTypeException if the tag exists and is not of the expected type (if specified)
	 */
	public function getTag(string $name, string $expectedClass = Tag::class) : ?Tag{
		assert(is_a($expectedClass, Tag::class, true));
		$tag = $this->value[$name] ?? null;
		if($tag !== null and !($tag instanceof $expectedClass)){
			throw new UnexpectedTagTypeException("Expected a tag of type $expectedClass, got " . get_class($tag));
		}

		return $tag;
	}

	/**
	 * Returns the ListTag with the specified name, or null if it does not exist. Triggers an exception if a tag exists
	 * with that name and the tag is not a ListTag.
	 *
	 * @param string $name
	 * @return ListTag|null
	 */
	public function getListTag(string $name) : ?ListTag{
		return $this->getTag($name, ListTag::class);
	}

	/**
	 * Returns the CompoundTag with the specified name, or null if it does not exist. Triggers an exception if a tag
	 * exists with that name and the tag is not a CompoundTag.
	 *
	 * @param string $name
	 * @return CompoundTag|null
	 */
	public function getCompoundTag(string $name) : ?CompoundTag{
		return $this->getTag($name, CompoundTag::class);
	}

	/**
	 * Sets the specified Tag as a child tag of the CompoundTag at the offset specified by the tag's name.
	 *
	 * @param string $name
	 * @param Tag    $tag
	 *
	 * @return $this
	 */
	public function setTag(string $name, Tag $tag) : self{
		$this->value[$name] = $tag;
		return $this;
	}

	/**
	 * Removes the child tags with the specified names from the CompoundTag. This function accepts a variadic list of
	 * strings.
	 *
	 * @param string ...$names
	 */
	public function removeTag(string ...$names) : void{
		foreach($names as $name){
			unset($this->value[$name]);
		}
	}

	/**
	 * Returns whether the CompoundTag contains a child tag with the specified name.
	 *
	 * @param string $name
	 * @param string $expectedClass
	 *
	 * @return bool
	 */
	public function hasTag(string $name, string $expectedClass = Tag::class) : bool{
		assert(is_a($expectedClass, Tag::class, true));
		return ($this->value[$name] ?? null) instanceof $expectedClass;
	}

	/**
	 * Returns the value of the child tag with the specified name, or $default if the tag doesn't exist. If the child
	 * tag is not of type $expectedType, an exception will be thrown.
	 *
	 * @param string $name
	 * @param string $expectedClass
	 * @param mixed  $default
	 *
	 * @return mixed
	 *
	 * @throws UnexpectedTagTypeException
	 * @throws NoSuchTagException
	 */
	public function getTagValue(string $name, string $expectedClass, $default = null){
		$tag = $this->getTag($name, $expectedClass);
		if($tag instanceof $expectedClass){
			return $tag->getValue();
		}

		if($default === null){
			throw new NoSuchTagException("Tag \"$name\" does not exist");
		}

		return $default;
	}

	/*
	 * The following methods are wrappers around getTagValue() with type safety.
	 */

	/**
	 * @param string   $name
	 * @param int|null $default
	 *
	 * @return int
	 */
	public function getByte(string $name, ?int $default = null) : int{
		return $this->getTagValue($name, ByteTag::class, $default);
	}

	/**
	 * @param string   $name
	 * @param int|null $default
	 *
	 * @return int
	 */
	public function getShort(string $name, ?int $default = null) : int{
		return $this->getTagValue($name, ShortTag::class, $default);
	}

	/**
	 * @param string   $name
	 * @param int|null $default
	 *
	 * @return int
	 */
	public function getInt(string $name, ?int $default = null) : int{
		return $this->getTagValue($name, IntTag::class, $default);
	}

	/**
	 * @param string   $name
	 * @param int|null $default
	 *
	 * @return int
	 */
	public function getLong(string $name, ?int $default = null) : int{
		return $this->getTagValue($name, LongTag::class, $default);
	}

	/**
	 * @param string     $name
	 * @param float|null $default
	 *
	 * @return float
	 */
	public function getFloat(string $name, ?float $default = null) : float{
		return $this->getTagValue($name, FloatTag::class, $default);
	}

	/**
	 * @param string     $name
	 * @param float|null $default
	 *
	 * @return float
	 */
	public function getDouble(string $name, ?float $default = null) : float{
		return $this->getTagValue($name, DoubleTag::class, $default);
	}

	/**
	 * @param string      $name
	 * @param string|null $default
	 *
	 * @return string
	 */
	public function getByteArray(string $name, ?string $default = null) : string{
		return $this->getTagValue($name, ByteArrayTag::class, $default);
	}

	/**
	 * @param string      $name
	 * @param string|null $default
	 *
	 * @return string
	 */
	public function getString(string $name, ?string $default = null) : string{
		return $this->getTagValue($name, StringTag::class, $default);
	}

	/**
	 * @param string     $name
	 * @param int[]|null $default
	 *
	 * @return int[]
	 */
	public function getIntArray(string $name, ?array $default = null) : array{
		return $this->getTagValue($name, IntArrayTag::class, $default);
	}

	/*
	 * The following methods are wrappers around setTag() which create appropriate tag objects on the fly.
	 */

	/**
	 * @param string $name
	 * @param int    $value
	 *
	 * @return $this
	 */
	public function setByte(string $name, int $value) : self{
		return $this->setTag($name, new ByteTag($value));
	}

	/**
	 * @param string $name
	 * @param int    $value
	 *
	 * @return $this
	 */
	public function setShort(string $name, int $value) : self{
		return $this->setTag($name, new ShortTag($value));
	}

	/**
	 * @param string $name
	 * @param int    $value
	 *
	 * @return $this
	 */
	public function setInt(string $name, int $value) : self{
		return $this->setTag($name, new IntTag($value));
	}

	/**
	 * @param string $name
	 * @param int    $value
	 *
	 * @return $this
	 */
	public function setLong(string $name, int $value) : self{
		return $this->setTag($name, new LongTag($value));
	}

	/**
	 * @param string $name
	 * @param float  $value
	 *
	 * @return $this
	 */
	public function setFloat(string $name, float $value) : self{
		return $this->setTag($name, new FloatTag($value));
	}

	/**
	 * @param string $name
	 * @param float  $value
	 *
	 * @return $this
	 */
	public function setDouble(string $name, float $value) : self{
		return $this->setTag($name, new DoubleTag($value));
	}

	/**
	 * @param string $name
	 * @param string $value
	 *
	 * @return $this
	 */
	public function setByteArray(string $name, string $value) : self{
		return $this->setTag($name, new ByteArrayTag($value));
	}

	/**
	 * @param string $name
	 * @param string $value
	 *
	 * @return $this
	 */
	public function setString(string $name, string $value) : self{
		return $this->setTag($name, new StringTag($value));
	}

	/**
	 * @param string $name
	 * @param int[]  $value
	 *
	 * @return $this
	 */
	public function setIntArray(string $name, array $value) : self{
		return $this->setTag($name, new IntArrayTag($value));
	}


	/**
	 * @param string $offset
	 *
	 * @return bool
	 */
	public function offsetExists($offset){
		return isset($this->value[$offset]);
	}

	/**
	 * @param string $offset
	 *
	 * @return mixed|null|\ArrayAccess
	 */
	public function offsetGet($offset){
		if(isset($this->value[$offset])){
			if($this->value[$offset] instanceof \ArrayAccess){
				return $this->value[$offset];
			}else{
				return $this->value[$offset]->getValue();
			}
		}

		assert(false, "Offset $offset not found");

		return null;
	}

	/**
	 * @param string $offset
	 * @param Tag    $value
	 *
	 * @throws \InvalidArgumentException if offset is null
	 * @throws \TypeError if $value is not a Tag object
	 */
	public function offsetSet($offset, $value){
		if($offset === null){
			throw new \InvalidArgumentException("Array access push syntax is not supported");
		}
		if($value instanceof Tag){
			$this->value[$offset] = $value;
		}else{
			throw new \TypeError("Value set by ArrayAccess must be an instance of " . Tag::class . ", got " . (is_object($value) ? " instance of " . get_class($value) : gettype($value)));
		}
	}

	public function offsetUnset($offset){
		unset($this->value[$offset]);
	}

	protected function getTypeName() : string{
		return "Compound";
	}

	public function getType() : int{
		return NBT::TAG_Compound;
	}

	public static function read(NbtStreamReader $reader, ReaderTracker $tracker) : self{
		$result = new self;
		$tracker->protectDepth(static function() use($reader, $tracker, $result){
			for($type = $reader->readByte(); $type !== NBT::TAG_End; $type = $reader->readByte()){
				$name = $reader->readString();
				$tag = NBT::createTag($type, $reader, $tracker);
				if($result->hasTag($name)){
					throw new NbtDataException("Duplicate key \"$name\"");
				}
				$result->setTag($name, $tag);
			}
		});
		return $result;
	}

	public function write(NbtStreamWriter $writer) : void{
		foreach($this->value as $name => $tag){
			if(is_int($name)){
				//PHP sucks
				//we only cast on seeing an int, because forcibly casting other types might conceal bugs.
				$name = (string) $name;
			}
			$writer->writeByte($tag->getType());
			$writer->writeString($name);
			$tag->write($writer);
		}
		$writer->writeByte(NBT::TAG_End);
	}

	protected function stringifyValue(int $indentation) : string{
		$str = "{\n";
		foreach($this->value as $name => $tag){
			$str .= str_repeat("  ", $indentation + 1) . "\"$name\" => " . $tag->toString($indentation + 1) . "\n";
		}
		return $str . str_repeat("  ", $indentation) . "}";
	}

	public function __clone(){
		foreach($this->value as $key => $tag){
			$this->value[$key] = $tag->safeClone();
		}
	}

	protected function makeCopy(){
		return clone $this;
	}

	public function next() : void{
		next($this->value);
	}

	/**
	 * @return bool
	 */
	public function valid() : bool{
		return key($this->value) !== null;
	}

	/**
	 * @return string|null
	 */
	public function key() : ?string{
		$k = key($this->value);
		if(is_int($k)){
			/* PHP arrays are idiotic and cast keys like "1" to int(1)
			 * TODO: perhaps we should consider using a \Ds\Map for this?
			 */
			$k = (string) $k;
		}

		return $k;
	}

	/**
	 * @return Tag|null
	 */
	public function current() : ?Tag{
		return current($this->value) ?: null;
	}

	public function rewind() : void{
		reset($this->value);
	}

	public function equals(Tag $that) : bool{
		if(!($that instanceof $this) or $this->count() !== $that->count()){
			return false;
		}

		foreach($this as $k => $v){
			$other = $that->getTag($k);
			if($other === null or !$v->equals($other)){
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns a copy of this CompoundTag with values from the given CompoundTag merged into it. Tags that exist both in
	 * this tag and the other will be overwritten by the tag in the other.
	 *
	 * This deep-clones all tags.
	 *
	 * @param CompoundTag $other
	 *
	 * @return CompoundTag
	 */
	public function merge(CompoundTag $other) : CompoundTag{
		$new = clone $this;

		foreach($other as $k => $namedTag){
			$new->setTag($k, clone $namedTag);
		}

		return $new;
	}
}
