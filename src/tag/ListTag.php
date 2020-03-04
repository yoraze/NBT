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
use pocketmine\nbt\NbtStreamReader;
use pocketmine\nbt\NbtStreamWriter;
use pocketmine\nbt\ReaderTracker;
use function func_num_args;
use function get_class;
use function gettype;
use function is_object;
use function str_repeat;

final class ListTag extends Tag implements \ArrayAccess, \Countable, \Iterator{
	use NoDynamicFieldsTrait;

	/** @var int */
	private $tagType;
	/** @var \SplDoublyLinkedList|Tag[] */
	private $value;

	/**
	 * @param Tag[] $value
	 * @param int   $tagType
	 */
	public function __construct(array $value = [], int $tagType = NBT::TAG_End){
		self::restrictArgCount(__METHOD__, func_num_args(), 2);
		$this->tagType = $tagType;
		$this->value = new \SplDoublyLinkedList();
		foreach($value as $tag){
			$this->push($tag);
		}
	}

	/**
	 * @return Tag[]
	 */
	public function getValue() : array{
		$value = [];
		foreach($this->value as $k => $v){
			$value[$k] = $v;
		}

		return $value;
	}

	/**
	 * Returns an array of tag values inserted into this list. ArrayAccess-implementing tags are returned as themselves
	 * (such as ListTag and CompoundTag) and others are returned as primitive values or arrays.
	 *
	 * @return array
	 */
	public function getAllValues() : array{
		$result = [];
		foreach($this->value as $tag){
			if($tag instanceof \ArrayAccess){
				$result[] = $tag;
			}else{
				$result[] = $tag->getValue();
			}
		}

		return $result;
	}

	/**
	 * @param int $offset
	 *
	 * @return bool
	 */
	public function offsetExists($offset) : bool{
		return isset($this->value[$offset]);
	}

	/**
	 * @param int $offset
	 *
	 * @return CompoundTag|ListTag|mixed
	 */
	public function offsetGet($offset){
		/** @var Tag|null $value */
		$value = $this->value[$offset] ?? null;

		if($value instanceof \ArrayAccess){
			return $value;
		}elseif($value !== null){
			return $value->getValue();
		}

		return null;
	}

	/**
	 * @param int|null $offset
	 * @param Tag      $value
	 *
	 * @throws \TypeError if an incompatible tag type is given
	 * @throws \TypeError if $value is not a Tag object
	 */
	public function offsetSet($offset, $value) : void{
		if($value instanceof Tag){
			$this->checkTagType($value);
			$this->value[$offset] = $value;
		}else{
			throw new \TypeError("Value set by ArrayAccess must be an instance of " . Tag::class . ", got " . (is_object($value) ? " instance of " . get_class($value) : gettype($value)));
		}
	}

	/**
	 * @param int $offset
	 */
	public function offsetUnset($offset) : void{
		unset($this->value[$offset]);
	}

	/**
	 * @return int
	 */
	public function count() : int{
		return $this->value->count();
	}

	/**
	 * @return int
	 */
	public function getCount() : int{
		return $this->value->count();
	}

	/**
	 * Appends the specified tag to the end of the list.
	 *
	 * @param Tag $tag
	 */
	public function push(Tag $tag) : void{
		$this->checkTagType($tag);
		$this->value->push($tag);
	}

	/**
	 * Removes the last tag from the list and returns it.
	 *
	 * @return Tag
	 */
	public function pop() : Tag{
		return $this->value->pop();
	}

	/**
	 * Adds the specified tag to the start of the list.
	 *
	 * @param Tag $tag
	 */
	public function unshift(Tag $tag) : void{
		$this->checkTagType($tag);
		$this->value->unshift($tag);
	}

	/**
	 * Removes the first tag from the list and returns it.
	 *
	 * @return Tag
	 */
	public function shift() : Tag{
		return $this->value->shift();
	}

	/**
	 * Inserts a tag into the list between existing tags, at the specified offset. Later values in the list are moved up
	 * by 1 position.
	 *
	 * @param int $offset
	 * @param Tag $tag
	 *
	 * @throws \OutOfRangeException if the offset is not within the bounds of the list
	 */
	public function insert(int $offset, Tag $tag){
		$this->checkTagType($tag);
		$this->value->add($offset, $tag);
	}

	/**
	 * Removes a value from the list. All later tags in the list are moved down by 1 position.
	 *
	 * @param int $offset
	 */
	public function remove(int $offset) : void{
		unset($this->value[$offset]);
	}

	/**
	 * Returns the tag at the specified offset.
	 *
	 * @param int $offset
	 *
	 * @return Tag
	 * @throws \OutOfRangeException if the offset is not within the bounds of the list
	 */
	public function get(int $offset) : Tag{
		return $this->value[$offset];
	}

	/**
	 * Returns the element in the first position of the list, without removing it.
	 *
	 * @return Tag
	 */
	public function first() : Tag{
		return $this->value->bottom();
	}

	/**
	 * Returns the element in the last position in the list (the end), without removing it.
	 *
	 * @return Tag
	 */
	public function last() : Tag{
		return $this->value->top();
	}

	/**
	 * Overwrites the tag at the specified offset.
	 *
	 * @param int $offset
	 * @param Tag $tag
	 *
	 * @throws \OutOfRangeException if the offset is not within the bounds of the list
	 */
	public function set(int $offset, Tag $tag) : void{
		$this->checkTagType($tag);
		$this->value[$offset] = $tag;
	}

	/**
	 * Returns whether a tag exists at the specified offset.
	 *
	 * @param int $offset
	 *
	 * @return bool
	 */
	public function isset(int $offset) : bool{
		return isset($this->value[$offset]);
	}

	/**
	 * Returns whether there are any tags in the list.
	 *
	 * @return bool
	 */
	public function empty() : bool{
		return $this->value->isEmpty();
	}

	protected function getTypeName() : string{
		return "List";
	}

	public function getType() : int{
		return NBT::TAG_List;
	}

	/**
	 * Returns the type of tag contained in this list.
	 *
	 * @return int
	 */
	public function getTagType() : int{
		return $this->tagType;
	}

	/**
	 * Sets the type of tag that can be added to this list. If TAG_End is used, the type will be auto-detected from the
	 * first tag added to the list.
	 *
	 * @param int $type
	 * @throws \LogicException if the list is not empty
	 */
	public function setTagType(int $type){
		if(!$this->value->isEmpty()){
			throw new \LogicException("Cannot change tag type of non-empty ListTag");
		}
		$this->tagType = $type;
	}

	/**
	 * Type-checks the given Tag for addition to the list, updating the list tag type as appropriate.
	 *
	 * @param Tag $tag
	 *
	 * @throws \TypeError if the tag type is not compatible.
	 */
	private function checkTagType(Tag $tag) : void{
		$type = $tag->getType();
		if($type !== $this->tagType){
			if($this->tagType === NBT::TAG_End){
				$this->tagType = $type;
			}else{
				//TODO: reintroduce type info
				throw new \TypeError("Invalid tag of type " . get_class($tag) . " assigned to ListTag");
			}
		}
	}

	public static function read(NbtStreamReader $reader, ReaderTracker $tracker) : self{
		$value = [];
		$tagType = $reader->readByte();
		$size = $reader->readInt();

		if($size > 0){
			if($tagType === NBT::TAG_End){
				throw new \UnexpectedValueException("Unexpected non-empty list of TAG_End");
			}

			$tracker->protectDepth(static function() use($size, $tagType, $reader, $tracker, &$value){
				for($i = 0; $i < $size; ++$i){
					$value[] = NBT::createTag($tagType, $reader, $tracker);
				}
			});
		}else{
			$tagType = NBT::TAG_End; //Some older NBT implementations used TAG_Byte for empty lists.
		}
		return new self($value, $tagType);
	}

	public function write(NbtStreamWriter $writer) : void{
		$writer->writeByte($this->tagType);
		$writer->writeInt($this->value->count());
		/** @var Tag $tag */
		foreach($this->value as $tag){
			$tag->write($writer);
		}
	}

	protected function stringifyValue(int $indentation) : string{
		$str = "{\n";
		/** @var Tag $tag */
		foreach($this->value as $tag){
			$str .= str_repeat("  ", $indentation + 1) . $tag->toString($indentation + 1) . "\n";
		}
		return $str . str_repeat("  ", $indentation) . "}";
	}

	public function __clone(){
		$new = new \SplDoublyLinkedList();

		foreach($this->value as $tag){
			$new->push($tag->safeClone());
		}

		$this->value = $new;
	}

	protected function makeCopy(){
		return clone $this;
	}

	public function next() : void{
		$this->value->next();
	}

	/**
	 * @return bool
	 */
	public function valid() : bool{
		return $this->value->valid();
	}

	/**
	 * @return Tag|null
	 */
	public function current() : ?Tag{
		return $this->value->current();
	}

	/**
	 * @return int
	 */
	public function key() : int{
		return (int) $this->value->key();
	}

	public function rewind() : void{
		$this->value->rewind();
	}

	public function equals(Tag $that) : bool{
		if(!($that instanceof $this) or $this->count() !== $that->count()){
			return false;
		}

		foreach($this as $k => $v){
			if(!$v->equals($that->get($k))){
				return false;
			}
		}

		return true;
	}
}
