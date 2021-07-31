<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\db\doctrine;

/**
 * Map phpBB's database types to Doctrine's portable types.
 */
class type_converter
{
	/**
	 * Type map.
	 *
	 * @var array
	 */
	private const TYPE_MAP = [
		'BINT'		=> ['bigint', []],
		'ULINT'		=> ['integer', ['unsigned' => true]],
		'UINT'		=> ['integer', ['unsigned' => true]],
		'TINT'		=> ['smallint', []],
		'USINT'		=> ['smallint', ['unsigned' => true]],
		'BOOL'		=> ['boolean', ['unsigned' => true]],
		'VCHAR'		=> ['string', ['length' => 255]],
		'CHAR'		=> ['ascii_string', []],
		'XSTEXT'	=> ['ascii_string', ['length' => 1000]],
		'XSTEXT_UNI'=> ['string', ['length' => 100]],
		'STEXT'		=> ['ascii_string', ['length' => 3000]],
		'STEXT_UNI'	=> ['string', ['length' => 255]],
		'TEXT'		=> ['text', ['length' => ((1 << 16) - 1)]],
		'TEXT_UNI'	=> ['text', ['length' => ((1 << 16) - 1)]],
		'MTEXT'		=> ['text', ['length' => ((1 << 24) - 1)]],
		'MTEXT_UNI'	=> ['text', ['length' => ((1 << 24) - 1)]],
		'TIMESTAMP'	=> ['integer', ['unsigned' => true]],
		'DECIMAL'	=> ['integer', ['precision' => 5, 'scale' => 2]],
		'PDECIMAL'	=> ['integer', ['precision' => 6, 'scale' => 3]],
		'VCHAR_UNI'	=> ['string', ['length' => 255]],
		'VCHAR_CI'	=> ['string_ci', ['length' => 255]],
		'VARBINARY'	=> ['binary', ['length' => 255]],
	];

	/**
	 * Convert legacy type to Doctrine's type system.
	 *
	 * @param string $type Legacy type name
	 *
	 * @return array<string, array> Pair of type name and options.
	 */
	public static function convert(string $type): array
	{
		if (strpos($type, ':') !== false)
		{
			list($typename, $length) = explode(':', $type);
			return self::mapWithLength($typename, (int) $length);
		}

		return self::mapType($type);
	}

	/**
	 * Map legacy types with length attribute.
	 *
	 * @param string	$type	Legacy type name.
	 * @param int		$length	Type length.
	 *
	 * @return array<string, array> Pair of type name and options.
	 */
	private static function mapWithLength(string $type, int $length): array
	{
		switch ($type)
		{
			case 'UINT':
			case 'INT':
			case 'TINT':
				return self::TYPE_MAP[$type];

			case 'DECIMAL':
			case 'PDECIMAL':
				$pair = self::TYPE_MAP[$type];
				$pair[1]['precision'] = $length;
				return $pair;

			case 'VCHAR':
			case 'CHAR':
			case 'VCHAR_UNI':
				$pair = self::TYPE_MAP[$type];
				$pair[1]['length'] = $length;
				return $pair;

			default:
				throw new \InvalidArgumentException("Database type is undefined.");
		}
	}

	/**
	 * Map phpBB's legacy database types to Doctrine types.
	 *
	 * @param string $type Type name.
	 *
	 * @return array<string, array> Pair of type name and an array of options.
	 */
	private static function mapType(string $type): array
	{
		if (!in_array($type, self::TYPE_MAP, true))
		{
			throw new \InvalidArgumentException("Database type is undefined.");
		}

		return self::TYPE_MAP[$type];
	}
}
