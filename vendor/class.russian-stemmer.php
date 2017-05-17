<?php

/**
 * Russian keyword stemmer based on @link http://forum.dklab.ru/php/advises/HeuristicWithoutTheDictionaryExtractionOfARootFromRussianWord.html
 * Released under GPLv2
 */

// $Id: russianstemmer.module,v 1.2.2.1 2010/03/23 06:53:40 frjo Exp $

/**
 * @file
 * "Improve Russian language searching by simplifying related words to their root (verbs, plurals, ...).
 * Algorithm based on http://snowball.tartarus.org/algorithms/russian/stemmer.html.
 */

class SearchWP_Russian_Stemmer {

	private $VERSION = "1.0";
	private $Stem_Caching = 0;
	private $Stem_Cache = array();
	private $VOWEL = '/аеиоуыэюя/';
	private $PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[ая])(в|вши|вшись)))$/';
	private $REFLEXIVE = '/(с[яь])$/';
	private $ADJECTIVE = '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|ему|ому|их|ых|ую|юю|ая|яя|ою|ею)$/';
	private $PARTICIPLE = '/((ивш|ывш|ующ)|((?<=[ая])(ем|нн|вш|ющ|щ)))$/';
	private $VERB = '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ен|ило|ыло|ено|ят|ует|уют|ит|ыт|ены|ить|ыть|ишь|ую|ю)|((?<=[ая])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/';
	private $NOUN = '/(а|ев|ов|ие|ье|е|иями|ями|ами|еи|ии|и|ией|ей|ой|ий|й|иям|ям|ием|ем|ам|ом|о|у|ах|иях|ях|ы|ь|ию|ью|ю|ия|ья|я)$/';
	private $RVRE = '/^(.*?[аеиоуыэюя])(.*)$/';
	private $DERIVATIONAL = '/[^аеиоуыэюя][аеиоуыэюя]+[^аеиоуыэюя]+[аеиоуыэюя].*(?<=о)сть?$/';

	/**
	 * Implementation of hook_search_preprocess.
	 *
	 * @param $text
	 *
	 * @return string
	 */
	function russianstemmer_search_preprocess( $text ) { //print_r($text).PHP_EOL;
		// Split words from noise and remove apostrophes
		//$words = preg_split( '/([^a-zA-Zа-я\']+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$words = preg_split( '/([^a-zA-Zа-яА-ЯёéåäöÅÄÖ\']+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		//$words = explode( ' ', $text );
		//echo '-----' . PHP_EOL;
		//print_r($text);
		//echo PHP_EOL;
		//print_r($words);
		//echo '-----' . PHP_EOL.PHP_EOL;
		//var_dump($text);
		//var_dump($words);

		// Process each word
		$odd = true;
		foreach ( $words as $k => $word ) {
			if ( $odd ) {
				$words[ $k ] = $this->russianstemmer_stem( $word );
			}
			$odd = ! $odd;
		}

		$sentence = implode( '', $words );

		//print_r($sentence); echo PHP_EOL;

		return $sentence;
	}

	function s( &$s, $re, $to ) {
		$orig = $s;
		$s    = preg_replace( $re, $to, $s );

		return $orig !== $s;
	}

	function m( $s, $re ) {
		return preg_match( $re, $s );
	}

	function mb_str_replace( $needle, $replacement, $haystack ) {
		return implode( $replacement, mb_split( $needle, $haystack ) );
	}

	function russianstemmer_stem( $word ) {
		$word = function_exists( 'mb_strtolower' ) ? mb_strtolower( $word ) : strtolower( $word );
		//$word = mb_eregi_replace( '/ё/', 'е', $word );
		$word = $this->mb_str_replace( 'ё', 'е', $word ); //echo phpversion();

		//print_r($word);
		# Check against cache of stemmed words
		if ( $this->Stem_Caching && isset( $this->Stem_Cache[ $word ] ) ) {
			return $this->Stem_Cache[ $word ];
		}
		$stem = $word;
		do {
			if ( ! preg_match( $this->RVRE, $word, $p ) ) {
				break;
			}
			$start = $p[1];
			$RV    = $p[2];
			if ( ! $RV ) {
				break;
			}

			# Step 1
			if ( ! $this->s( $RV, $this->PERFECTIVEGROUND, '' ) ) {
				$this->s( $RV, $this->REFLEXIVE, '' );

				if ( $this->s( $RV, $this->ADJECTIVE, '' ) ) {
					$this->s( $RV, $this->PARTICIPLE, '' );
				} else {
					if ( ! $this->s( $RV, $this->VERB, '' ) ) {
						$this->s( $RV, $this->NOUN, '' );
					}
				}
			}

			# Step 2
			$this->s( $RV, '/и$/', '' );

			# Step 3
			if ( $this->m( $RV, $this->DERIVATIONAL ) ) {
				$this->s( $RV, '/ость?$/', '' );
			}

			# Step 4
			if ( ! $this->s( $RV, '/ь$/', '' ) ) {
				$this->s( $RV, '/ейше?/', '' );
				$this->s( $RV, '/нн$/', 'н' );
			}

			$stem = $start . $RV;
		} while ( false );
		if ( $this->Stem_Caching ) {
			$this->Stem_Cache[ $word ] = $stem;
		} //print_r($stem);echo PHP_EOL;

		return $stem;
	}

	function stem_caching( $parm_ref ) {
		$caching_level = @$parm_ref['-level'];
		if ( $caching_level ) {
			if ( ! $this->m( $caching_level, '/^[012]$/' ) ) {
				die( __CLASS__ . "::stem_caching() - Legal values are '0','1' or '2'. '$caching_level' is not a legal value" );
			}
			$this->Stem_Caching = $caching_level;
		}

		return $this->Stem_Caching;
	}

	function clear_stem_cache() {
		$this->Stem_Cache = array();
	}
}