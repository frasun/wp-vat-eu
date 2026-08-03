<?php
/**
 * VAT validation patterns
 *
 * @link https://en.wikipedia.org/wiki/VAT_identification_number
 *
 * @package Chocante_VAT_EU
 */

namespace WP_VAT_EU;

const COUNTRY_PATTERNS = array(
	// Austria.
	'AT' => array(
		'pattern'   => '/^(AT)?U\d{8}/',
		'validator' => __NAMESPACE__ . '\validator_at',
	),
	// Belgium.
	'BE' => array(
		'pattern'   => '/^(BE)?\d{10}/',
		'validator' => __NAMESPACE__ . '\validator_be',
	),
	// Bulgaria.
	'BG' => array(
		'pattern'   => '/^(BG)?\d{9,10}/',
		'validator' => __NAMESPACE__ . '\validator_bg',
	),
	// Croatia.
	'HR' => array(
		'pattern'   => '/^(HR)?\d{11}/',
		'validator' => __NAMESPACE__ . '\validator_hr',
	),
	// Cyprus.
	'CY' => array(
		'pattern' => '/^(CY)?[A-Z0-9]{8}/',
	),
	// Czech Republic.
	'CZ' => array(
		'pattern'   => '/^(CZ)?\d{8,10}/',
		'validator' => __NAMESPACE__ . '\validator_cz',
	),
	// Denmark.
	'DK' => array(
		'pattern'   => '/^(DK)?\d{8}/',
		'validator' => __NAMESPACE__ . '\validator_dk',
	),
	// Estonia.
	'EE' => array(
		'pattern'   => '/^(EE)?\d{9}/',
		'validator' => __NAMESPACE__ . '\validator_ee',
	),
	// Finland.
	'FI' => array(
		'pattern'   => '/^(FI)?\d{8}/',
		'validator' => __NAMESPACE__ . '\validator_fi',
	),
	// France.
	'FR' => array(
		'pattern'   => '/^(FR)?[A-Z0-9]{2}\d{9}/',
		'validator' => __NAMESPACE__ . '\validator_fr',
	),
	// Germany.
	'DE' => array(
		'pattern'   => '/^(DE)?\d{9}/',
		'validator' => __NAMESPACE__ . '\validator_de',
	),
	// United Kingdom.
	'GB' => array(
		'pattern' => '/^(GB)?\d{9}/',
	),
	// Greece.
	'GR' => array(
		'pattern'   => '/^(EL)?\d{9}/',
		'validator' => __NAMESPACE__ . '\validator_gr',
		'prefix'    => 'EL',
	),
	// Hungary.
	'HU' => array(
		'pattern'   => '/^(HU)?\d{8}/',
		'validator' => __NAMESPACE__ . '\validator_hu',
	),
	// Ireland.
	'IE' => array(
		'pattern'   => '/^(IE)?\d{7}[A-Z]{1,2}/',
		'validator' => __NAMESPACE__ . '\validator_ie',
	),
	// Italy.
	'IT' => array(
		'pattern'   => '/^(IT)?\d{11}/',
		'validator' => __NAMESPACE__ . '\validator_it',
	),
	// Latvia.
	'LV' => array(
		'pattern'   => '/^(LV)?\d{11}/',
		'validator' => __NAMESPACE__ . '\validator_lv',
	),
	// Lithuania.
	'LT' => array(
		'pattern'   => '/^(LT)?(\d{9}|\d{12})/',
		'validator' => __NAMESPACE__ . '\validator_lt',
	),
	// Luxembourg.
	'LU' => array(
		'pattern'   => '/^(LU)?\d{8}/',
		'validator' => __NAMESPACE__ . '\validator_lu',
	),
	// Malta.
	'MT' => array(
		'pattern'   => '/^(MT)?\d{8}/',
		'validator' => __NAMESPACE__ . '\validator_mt',
	),
	// Monaco.
	'MC' => array(
		'pattern' => '/^(FR)?[A-Z0-9]{2}\d{9}/',
	),
	// Netherlands.
	'NL' => array(
		'pattern'   => '/^(NL)?\d{9}B\d{2}/',
		'validator' => __NAMESPACE__ . '\validator_nl',
	),
	// Northern Ireland.
	'XI' => array(
		'pattern' => '/^(XI)?\d{9}/',
	),
	// Poland.
	'PL' => array(
		'pattern'   => '/^(PL)?\d{10}/',
		'validator' => __NAMESPACE__ . '\validator_pl',
	),
	// Portugal.
	'PT' => array(
		'pattern'   => '/^(PT)?\d{9}/',
		'validator' => __NAMESPACE__ . '\validator_pt',
	),
	// Romania.
	'RO' => array(
		'pattern' => '/^(RO)?\d{2,10}/',
	),
	// Slovakia.
	'SK' => array(
		'pattern'   => '/^(SK)?\d{10}/',
		'validator' => __NAMESPACE__ . '\validator_sk',
	),
	// Slovenia.
	'SI' => array(
		'pattern'   => '/^(SI)?\d{8}/',
		'validator' => __NAMESPACE__ . '\validator_si',
	),
	// Spain.
	'ES' => array(
		'pattern' => '/^(ES)?[A-Z](\d{8}|\d{7}[A-Z])/',
	),
	// Sweden.
	'SE' => array(
		'pattern'   => '/^(SE)?\d{12}/',
		'validator' => __NAMESPACE__ . '\validator_se',
	),
);

/**
 * Pattern validation (Austria)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_at( $vat ) {
	if ( 'U' !== $vat[0] ) {
		return false;
	}

	$vat = substr( $vat, 1 );

	$multipliers = array( 1, 2, 1, 2, 1, 2, 1 );
	$total       = 0;
	for ( $i = 0; $i < 7; $i++ ) {
		$temp   = (int) $vat[ $i ] * $multipliers[ $i ];
		$total += $temp > 9 ? (int) ( $temp / 10 ) + $temp % 10 : $temp;
	}
	$check = ( 10 - ( $total + 4 ) % 10 ) % 10;
	return $check === (int) $vat[7];
}

/**
 * Pattern validation (Belgium)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_be( $vat ) {
	if ( 9 === strlen( $vat ) ) {
		$vat = '0' . $vat;
	}
	// Modulus 97 check.
	return (int) substr( $vat, 8, 2 ) === ( 97 - (int) substr( $vat, 0, 8 ) % 97 );
}

/**
 * Pattern validation (Bulgaria)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_bg( $vat ) {
	if ( 9 === strlen( $vat ) ) {
		$total = 0;
		for ( $i = 0; $i < 8; $i++ ) {
			$total += (int) $vat[ $i ] * ( $i + 1 );
		}
		$check = $total % 11;
		if ( 10 === $check ) {
			$total = 0;
			for ( $i = 0; $i < 8; $i++ ) {
				$total += (int) $vat[ $i ] * ( $i + 3 );
			}
			$check = $total % 11;
			if ( 10 === $check ) {
				$check = 0;
			}
		}
		return (int) $vat[8] === $check;
	}
	// 10 digit: physical person.
	$multipliers = array( 2, 4, 8, 5, 10, 9, 7, 3, 6 );
	$total       = 0;
	for ( $i = 0; $i < 9; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = $total % 11;
	if ( 10 === $check ) {
		$check = 0;
	}
	if ( (int) $vat[9] === $check ) {
		return true;
	}
	// Foreigner.
	$multipliers = array( 21, 19, 17, 13, 11, 9, 7, 3, 1 );
	$total       = 0;
	for ( $i = 0; $i < 9; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	if ( (int) $vat[9] === $total % 10 ) {
		return true;
	}
	// Miscellaneous.
	$multipliers = array( 4, 3, 2, 7, 6, 5, 4, 3, 2 );
	$total       = 0;
	for ( $i = 0; $i < 9; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = 11 - $total % 11;
	if ( 10 === $check ) {
		return false;
	}
	if ( 11 === $check ) {
		$check = 0;
	}
	return (int) $vat[9] === $check;
}

/**
 * Pattern validation (Czech Republic)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_cz( $vat ) {
	$len = strlen( $vat );
	// 8-digit legal entities.
	if ( 8 === $len ) {
		$multipliers = array( 8, 7, 6, 5, 4, 3, 2 );
		$total       = 0;
		for ( $i = 0; $i < 7; $i++ ) {
			$total += (int) $vat[ $i ] * $multipliers[ $i ];
		}
		$check = 11 - $total % 11;
		if ( 10 === $check ) {
			$check = 0;
		}
		if ( 11 === $check ) {
			$check = 1;
		}
		return (int) $vat[7] === $check;
	}
	// 9/10-digit individuals: no checksum, just format.
	return true;
}

/**
 * Pattern validation (Germany)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_de( $vat ) {
	$product = 10;
	for ( $i = 0; $i < 8; $i++ ) {
		$sum = ( (int) $vat[ $i ] + $product ) % 10;
		if ( 0 === $sum ) {
			$sum = 10;
		}
		$product = ( 2 * $sum ) % 11;
	}
	$check = 1 === $product ? 0 : 11 - $product;
	return (int) $vat[8] === $check;
}

/**
 * Pattern validation (Denmark)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_dk( $vat ) {
	$multipliers = array( 2, 7, 6, 5, 4, 3, 2, 1 );
	$total       = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	return 0 === $total % 11;
}

/**
 * Pattern validation (Estonia)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_ee( $vat ) {
	$multipliers = array( 3, 7, 1, 3, 7, 1, 3, 7 );
	$total       = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = ( 10 - $total % 10 ) % 10;
	return (int) $vat[8] === $check;
}

/**
 * Pattern validation (Finland)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_fi( $vat ) {
	$multipliers = array( 7, 9, 10, 5, 8, 4, 2 );
	$total       = 0;
	for ( $i = 0; $i < 7; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = 11 - $total % 11;
	if ( $check > 9 ) {
		$check = 0;
	}
	return (int) $vat[7] === $check;
}

/**
 * Pattern validation (France)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_fr( $vat ) {
	// Only numeric keys can be verified.
	if ( ! preg_match( '/^\d{11}$/', $vat ) ) {
		return true;
	}
	$siren = (int) substr( $vat, 2 );
	$key   = ( 12 + 3 * ( $siren % 97 ) ) % 97;
	return (int) substr( $vat, 0, 2 ) === $key;
}

/**
 * Pattern validation (Greece)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_gr( $vat ) {
	if ( 8 === strlen( $vat ) ) {
		$vat = '0' . $vat;
	}
	$multipliers = array( 256, 128, 64, 32, 16, 8, 4, 2 );
	$total       = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = $total % 11;
	if ( $check > 9 ) {
		$check = 0;
	}
	return (int) $vat[8] === $check;
}

/**
 * Pattern validation (Croatia)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_hr( $vat ) {
	$product = 10;
	for ( $i = 0; $i < 10; $i++ ) {
		$sum = ( (int) $vat[ $i ] + $product ) % 10;
		if ( 0 === $sum ) {
			$sum = 10;
		}
		$product = ( 2 * $sum ) % 11;
	}
	return 1 === ( $product + (int) $vat[10] ) % 10;
}

/**
 * Pattern validation (Hungary)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_hu( $vat ) {
	$multipliers = array( 9, 7, 3, 1, 9, 7, 3 );
	$total       = 0;
	for ( $i = 0; $i < 7; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = ( 10 - $total % 10 ) % 10;
	return (int) $vat[7] === $check;
}

/**
 * Pattern validation (Ireland)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_ie( $vat ) {
	// Convert old format to new.
	if ( preg_match( '/^\d[A-Z\*\+]/', $vat ) ) {
		$vat = '0' . substr( $vat, 2, 5 ) . $vat[0] . $vat[7];
	}
	$multipliers = array( 8, 7, 6, 5, 4, 3, 2 );
	$total       = 0;
	for ( $i = 0; $i < 7; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	// Type 3: trailing A or H.
	if ( preg_match( '/^\d{7}[A-Z][AH]$/', $vat ) ) {
		$total += 'H' === $vat[8] ? 72 : 9;
	}
	$check = $total % 23;
	$check = 0 === $check ? 'W' : chr( $check + 64 );
	return $vat[7] === $check;
}

/**
 * Pattern validation (Italy)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_it( $vat ) {
	if ( 0 === (int) substr( $vat, 0, 7 ) ) {
		return false;
	}
	$office = (int) substr( $vat, 7, 3 );
	if ( $office < 1 || ( $office > 201 && 999 !== $office && 888 !== $office ) ) {
		return false;
	}
	$multipliers = array( 1, 2, 1, 2, 1, 2, 1, 2, 1, 2 );
	$total       = 0;
	for ( $i = 0; $i < 10; $i++ ) {
		$temp   = (int) $vat[ $i ] * $multipliers[ $i ];
		$total += $temp > 9 ? (int) ( $temp / 10 ) + $temp % 10 : $temp;
	}
	$check = ( 10 - $total % 10 ) % 10;
	return (int) $vat[10] === $check;
}

/**
 * Pattern validation (Lithuania)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_lt( $vat ) {
	$len = strlen( $vat );
	if ( 9 === $len ) {
		if ( '1' !== $vat[7] ) {
			return false;
		}
		$total = 0;
		for ( $i = 0; $i < 8; $i++ ) {
			$total += (int) $vat[ $i ] * ( $i + 1 );
		}
		if ( 10 === $total % 11 ) {
			$mult  = array( 3, 4, 5, 6, 7, 8, 9, 1 );
			$total = 0;
			for ( $i = 0; $i < 8; $i++ ) {
				$total += (int) $vat[ $i ] * $mult[ $i ];
			}
		}
		$check = $total % 11;
		if ( 10 === $check ) {
			$check = 0;
		}
		return (int) $vat[8] === $check;
	}
	// 12 digits.
	if ( '1' !== $vat[10] ) {
		return false;
	}
	$mult  = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 1, 2 );
	$total = 0;
	for ( $i = 0; $i < 11; $i++ ) {
		$total += (int) $vat[ $i ] * $mult[ $i ];
	}
	if ( 10 === $total % 11 ) {
		$mult  = array( 3, 4, 5, 6, 7, 8, 9, 1, 2, 3, 4 );
		$total = 0;
		for ( $i = 0; $i < 11; $i++ ) {
			$total += (int) $vat[ $i ] * $mult[ $i ];
		}
	}
	$check = $total % 11;
	if ( 10 === $check ) {
		$check = 0;
	}
	return (int) $vat[11] === $check;
}

/**
 * Pattern validation (Luxembourg)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_lu( $vat ) {
	return (int) substr( $vat, 6, 2 ) === (int) substr( $vat, 0, 6 ) % 89;
}

/**
 * Pattern validation (Latvia)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_lv( $vat ) {
	// Natural persons: first digit 0-3, check date format only.
	if ( preg_match( '/^[0-3]/', $vat ) ) {
		return (bool) preg_match( '/^[0-3]\d[0-1]\d/', $vat );
	}
	$multipliers = array( 9, 1, 4, 8, 3, 10, 2, 5, 7, 6 );
	$total       = 0;
	for ( $i = 0; $i < 10; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$mod = $total % 11;
	if ( 4 === $mod && '9' === $vat[0] ) {
		$total -= 45;
	}
	$mod   = $total % 11;
	$check = 4 === $mod ? 4 - $mod : ( $mod > 4 ? 14 - $mod : 3 - $mod );
	return (int) $vat[10] === $check;
}

/**
 * Pattern validation (Malta)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_mt( $vat ) {
	$multipliers = array( 3, 4, 6, 7, 8, 9 );
	$total       = 0;
	for ( $i = 0; $i < 6; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = 37 - $total % 37;
	return (int) substr( $vat, 6, 2 ) === $check;
}

/**
 * Pattern validation (Netherlands)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_nl( $vat ) {
	$multipliers = array( 9, 8, 7, 6, 5, 4, 3, 2 );
	$total       = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = $total % 11;
	if ( $check > 9 ) {
		$check = 0;
	}
	return (int) $vat[8] === $check;
}

/**
 * Pattern validation (Poland)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_pl( $vat ) {
	$multipliers = array( 6, 5, 7, 2, 3, 4, 5, 6, 7 );
	$total       = 0;
	for ( $i = 0; $i < 9; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = $total % 11;
	if ( $check > 9 ) {
		$check = 0;
	}
	return (int) $vat[9] === $check;
}

/**
 * Pattern validation (Portugal)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_pt( $vat ) {
	$multipliers = array( 9, 8, 7, 6, 5, 4, 3, 2 );
	$total       = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = 11 - $total % 11;
	if ( $check > 9 ) {
		$check = 0;
	}
	return (int) $vat[8] === $check;
}

/**
 * Pattern validation (Sweden)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_se( $vat ) {
	$r = 0;
	for ( $i = 0; $i < 9; $i += 2 ) {
		$d  = (int) $vat[ $i ];
		$r += (int) ( $d / 5 ) + ( $d * 2 ) % 10;
	}
	$s = 0;
	for ( $i = 1; $i < 9; $i += 2 ) {
		$s += (int) $vat[ $i ];
	}
	$check = ( 10 - ( $r + $s ) % 10 ) % 10;
	return (int) $vat[9] === $check;
}

/**
 * Pattern validation (Slovenia)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_si( $vat ) {
	$multipliers = array( 8, 7, 6, 5, 4, 3, 2 );
	$total       = 0;
	for ( $i = 0; $i < 7; $i++ ) {
		$total += (int) $vat[ $i ] * $multipliers[ $i ];
	}
	$check = 11 - $total % 11;
	if ( 10 === $check ) {
		$check = 0;
	}
	return 11 !== $check && (int) $vat[7] === $check;
}

/**
 * Pattern validation (Slovakia)
 *
 * @param string $vat VAT number.
 * @return bool
 */
function validator_sk( $vat ) {
	return 0 === (int) $vat % 11;
}
