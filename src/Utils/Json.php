<?php

namespace IgniteKit\WP\QueryBuilder\Utils;

class Json {

	/**
	 * Decodes object. If data is not valid JSON, returns data.
	 *
	 * @param $data
	 * @param  bool  $associative
	 * @since 1.1.1
	 *
	 * @return mixed
	 */
	public static function maybe_decode( $data, $associative = false ) {

		if ( is_null( $data ) ) {
			return null;
		}

		$result = json_decode( $data, $associative );

		return json_last_error() === JSON_ERROR_NONE ? $result : $data;
	}

}