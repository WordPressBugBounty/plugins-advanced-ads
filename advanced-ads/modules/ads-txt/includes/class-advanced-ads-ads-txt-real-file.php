<?php
/**
 * Real ads.txt file parser.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

/**
 * Represents a real ads.txt file.
 */
class Advanced_Ads_Ads_Txt_Real_File {
	/**
	 * Parsed ads.txt records as [ data, comments[] ] pairs.
	 *
	 * @var list<array{0: string, 1: list<string>}>
	 */
	private $records = [];

	/**
	 * Parse a real file.
	 *
	 * @param string $file File data.
	 */
	public function parse_file( $file ) {
		$comments = [];
		$lines    = preg_split( '/\r\n|\r|\n/', $file );

		foreach ( $lines as $line ) {
			$line = explode( '#', $line );

			$comment = isset( $line[1] ) ? trim( $line[1] ) : '';
			if ( '' !== $comment ) {
				$comments[] = '# ' . $comment;
			}

			if ( ! trim( $line[0] ) ) {
				continue;
			}

			$data = [];
			$rec  = explode( ',', $line[0] );

			foreach ( $rec as $k => $r ) {
				$r = trim( $r, " \n\r\t," );
				if ( $r ) {
					$data[] = $r;
				}
			}

			if ( $data ) {
				// Add the record and comments that were placed above or to the right of it.
				$this->add_record( implode( ', ', $data ), $comments );
			}

			$comments = [];
		}
	}

	/**
	 * Add record.
	 *
	 * @param string       $data     Record without comments.
	 * @param list<string> $comments Comments related to the record.
	 */
	private function add_record( $data, $comments = [] ) {
		$this->records[] = [ $data, $comments ];
	}

	/**
	 * Get records
	 *
	 * @return list<array{0: string, 1: list<string>}>
	 */
	public function get_records() {
		return $this->records;
	}

	/**
	 * Output file
	 *
	 * @return string
	 */
	public function output() {
		$r = '';
		foreach ( $this->records as $rec ) {
			foreach ( $rec[1] as $rec1 ) {
				$r .= $rec1 . "\n";
			}
			$r .= $rec[0] . "\n";
		}

		return $r;
	}

	/**
	 * Subtract another ads.txt file.
	 *
	 * @param Advanced_Ads_Ads_Txt_Real_File $subtrahend File whose records should be removed from this one.
	 *
	 * @return void
	 */
	public function subtract( Advanced_Ads_Ads_Txt_Real_File $subtrahend ): void {
		$r1 = $subtrahend->get_records();
		foreach ( $this->records as $k => $record ) {
			foreach ( $r1 as $r ) {
				if ( $record[0] === $r[0] ) {
					unset( $this->records[ $k ] );
				}
			}
		}
	}
}
