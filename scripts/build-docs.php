<?php
/**
 * Regenerates the merchant setup guide deliverables from docs/merchant-setup-guide.md.
 *
 * The markdown file is the source of truth. Never hand-edit the .docx or .pdf:
 * edit the markdown and run this script, otherwise the three copies drift apart.
 *
 * Usage:
 *   php scripts/build-docs.php [--skip-docx] [--skip-pdf]
 *
 * Both writers are self contained, so the only requirement is PHP with the zip
 * and gd extensions. The cover is stamped with the plugin header version, so
 * rerun this whenever the version changes.
 *
 * @package BCI_Woo_Plugin
 */

declare( strict_types = 1 );

/**
 * Turns the subset of markdown used by the setup guide into a block list.
 *
 * Blocks are arrays with a 'type' of heading, paragraph, image, list or code.
 * List items hold their own nested block lists so a screenshot can sit inside
 * the step it illustrates.
 */
final class Guide_Markdown {

	/**
	 * Parses a markdown document into blocks.
	 *
	 * @param string $markdown Markdown source.
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse( string $markdown ): array {
		$lines  = explode( "\n", str_replace( "\r\n", "\n", $markdown ) );
		$blocks = array();
		$index  = 0;

		while ( $index < count( $lines ) ) {
			$line = rtrim( $lines[ $index ] );

			if ( '' === trim( $line ) ) {
				++$index;
				continue;
			}

			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $line, $matches ) ) {
				$blocks[] = array(
					'type'  => 'heading',
					'level' => strlen( $matches[1] ),
					'text'  => trim( $matches[2] ),
				);
				++$index;
				continue;
			}

			if ( preg_match( '/^```(\w*)\s*$/', $line, $matches ) ) {
				$blocks[] = self::read_code_block( $lines, $index, $matches[1] );
				continue;
			}

			if ( self::is_list_item( $line ) ) {
				$blocks[] = self::read_list( $lines, $index );
				continue;
			}

			$image = self::read_image( $line );

			if ( null !== $image ) {
				$blocks[] = $image;
				++$index;
				continue;
			}

			$blocks[] = array(
				'type' => 'paragraph',
				'runs' => self::inline_runs( self::read_paragraph( $lines, $index ) ),
			);
		}

		return $blocks;
	}

	/**
	 * Splits inline markdown into styled runs.
	 *
	 * @param string $text Inline markdown.
	 * @return array<int, array<string, mixed>>
	 */
	public static function inline_runs( string $text ): array {
		$pieces = preg_split(
			'/(\*\*[^*]+\*\*|`[^`]+`|\[[^\]]+\]\([^)]+\))/',
			$text,
			-1,
			PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);

		$runs = array();

		foreach ( $pieces as $piece ) {
			if ( preg_match( '/^\*\*(.+)\*\*$/s', $piece, $matches ) ) {
				$runs[] = self::run( $matches[1], true, false, null );
				continue;
			}

			if ( preg_match( '/^`(.+)`$/s', $piece, $matches ) ) {
				$runs[] = self::run( $matches[1], false, true, null );
				continue;
			}

			if ( preg_match( '/^\[([^\]]+)\]\(([^)]+)\)$/s', $piece, $matches ) ) {
				$runs[] = self::run( $matches[1], false, false, $matches[2] );
				continue;
			}

			$runs[] = self::run( $piece, false, false, null );
		}

		return $runs;
	}

	/**
	 * Builds a single styled run.
	 *
	 * @param string      $text Run text.
	 * @param bool        $bold Whether the run is bold.
	 * @param bool        $code Whether the run is inline code.
	 * @param string|null $href Link target, when the run is a link.
	 * @return array<string, mixed>
	 */
	private static function run( string $text, bool $bold, bool $code, ?string $href ): array {
		return array(
			'text' => $text,
			'bold' => $bold,
			'code' => $code,
			'href' => $href,
		);
	}

	/**
	 * Reads a fenced code block starting at the fence line.
	 *
	 * @param array<int, string> $lines Document lines.
	 * @param int                $index Cursor, advanced past the block.
	 * @param string             $language Fence language.
	 * @return array<string, mixed>
	 */
	private static function read_code_block( array $lines, int &$index, string $language ): array {
		$content = array();
		++$index;

		while ( $index < count( $lines ) && ! preg_match( '/^```\s*$/', rtrim( $lines[ $index ] ) ) ) {
			$content[] = rtrim( $lines[ $index ] );
			++$index;
		}

		++$index;

		return array(
			'type'     => 'code',
			'language' => $language,
			'text'     => implode( "\n", $content ),
		);
	}

	/**
	 * Reads a list and any indented continuation blocks belonging to its items.
	 *
	 * @param array<int, string> $lines Document lines.
	 * @param int                $index Cursor, advanced past the list.
	 * @return array<string, mixed>
	 */
	private static function read_list( array $lines, int &$index ): array {
		$ordered = (bool) preg_match( '/^\d+\.\s+/', $lines[ $index ] );
		$items   = array();

		while ( $index < count( $lines ) ) {
			$line = rtrim( $lines[ $index ] );

			if ( '' === trim( $line ) ) {
				$lookahead = self::next_content_line( $lines, $index + 1 );

				if ( null === $lookahead ) {
					break;
				}

				// A blank line separates a step from its screenshot, and one step
				// from the next. In both cases the list is still running.
				$next      = rtrim( $lines[ $lookahead ] );
				$continues = preg_match( '/^\s{2,}\S/', $next )
					|| ( self::is_list_item( $next ) && $ordered === (bool) preg_match( '/^\d+\.\s+/', $next ) );

				if ( ! $continues ) {
					break;
				}

				$index = $lookahead;
				continue;
			}

			if ( self::is_list_item( $line ) ) {
				if ( $ordered !== (bool) preg_match( '/^\d+\.\s+/', $line ) ) {
					break;
				}

				preg_match( '/^(?:\d+\.|-)\s+(.*)$/', $line, $matches );
				$items[] = array(
					array(
						'type' => 'paragraph',
						'runs' => self::inline_runs( trim( $matches[1] ) ),
					),
				);
				++$index;
				continue;
			}

			if ( preg_match( '/^\s{2,}(.*)$/', $line, $matches ) && array() !== $items ) {
				$content = trim( $matches[1] );
				$image   = self::read_image( $content );
				$last    = count( $items ) - 1;

				$items[ $last ][] = null !== $image
					? $image
					: array(
						'type' => 'paragraph',
						'runs' => self::inline_runs( $content ),
					);
				++$index;
				continue;
			}

			break;
		}

		return array(
			'type'    => 'list',
			'ordered' => $ordered,
			'items'   => $items,
		);
	}

	/**
	 * Reads a paragraph, joining soft-wrapped lines.
	 *
	 * @param array<int, string> $lines Document lines.
	 * @param int                $index Cursor, advanced past the paragraph.
	 * @return string
	 */
	private static function read_paragraph( array $lines, int &$index ): string {
		$parts = array();

		while ( $index < count( $lines ) ) {
			$line = rtrim( $lines[ $index ] );

			if ( '' === trim( $line ) || self::is_list_item( $line ) || preg_match( '/^(#{1,6}\s|```)/', $line ) ) {
				break;
			}

			if ( array() !== $parts && null !== self::read_image( $line ) ) {
				break;
			}

			$parts[] = trim( $line );
			++$index;
		}

		return implode( ' ', $parts );
	}

	/**
	 * Parses an image-only line.
	 *
	 * @param string $line Candidate line.
	 * @return array<string, mixed>|null
	 */
	private static function read_image( string $line ): ?array {
		if ( ! preg_match( '/^!\[([^\]]*)\]\(([^)]+)\)$/', trim( $line ), $matches ) ) {
			return null;
		}

		return array(
			'type' => 'image',
			'alt'  => $matches[1],
			'src'  => $matches[2],
		);
	}

	/**
	 * Reports whether a line opens a list item.
	 *
	 * @param string $line Candidate line.
	 * @return bool
	 */
	private static function is_list_item( string $line ): bool {
		return 1 === preg_match( '/^(?:\d+\.|-)\s+/', $line );
	}

	/**
	 * Finds the next line with content.
	 *
	 * @param array<int, string> $lines Document lines.
	 * @param int                $from  First index to consider.
	 * @return int|null
	 */
	private static function next_content_line( array $lines, int $from ): ?int {
		for ( $index = $from; $index < count( $lines ); ++$index ) {
			if ( '' !== trim( $lines[ $index ] ) ) {
				return $index;
			}
		}

		return null;
	}
}

/**
 * Writes blocks into a Word document package.
 *
 * The package is assembled by hand rather than by a library so the script keeps
 * the repository dependency free. Only the parts Word needs are emitted.
 */
final class Docx_Writer {

	private const BRAND_COLOUR      = '00A1B2';
	private const TEXT_COLOUR       = '242424';
	private const EMU_PER_PIXEL     = 9525;
	private const CONTENT_WIDTH_EMU = 5731510;
	private const LIST_INDENT       = 720;
	private const BULLET_NUM_ID     = 1;

	/**
	 * Document body XML built so far.
	 *
	 * @var string
	 */
	private string $body = '';

	/**
	 * Image relationship id to absolute source path.
	 *
	 * @var array<string, string>
	 */
	private array $images = array();

	/**
	 * Hyperlink relationship id to target URL.
	 *
	 * @var array<string, string>
	 */
	private array $hyperlinks = array();

	/**
	 * Numbering instance ids that restart at one.
	 *
	 * @var array<int, int>
	 */
	private array $ordered_lists = array();

	private int $next_relationship = 10;
	private int $next_drawing      = 1;

	/**
	 * Directory the markdown image paths are relative to.
	 *
	 * @var string
	 */
	private string $asset_root;

	/**
	 * @param string $asset_root Directory the markdown image paths resolve against.
	 */
	public function __construct( string $asset_root ) {
		$this->asset_root = $asset_root;
	}

	/**
	 * Adds the cover heading used in place of the markdown title.
	 *
	 * @param string $title    Document title.
	 * @param string $subtitle Subtitle line.
	 * @return void
	 */
	public function add_cover( string $title, string $subtitle ): void {
		$this->body .= $this->paragraph(
			$this->text_run( $title, array( 'bold' => true, 'size' => 40, 'colour' => self::BRAND_COLOUR ) ),
			array( 'align' => 'center', 'space_after' => 0 )
		);
		$this->body .= $this->paragraph(
			$this->text_run( $subtitle, array( 'size' => 22, 'colour' => '595959' ) ),
			array( 'align' => 'center', 'space_after' => 360 )
		);
	}

	/**
	 * Appends a list of blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks to append.
	 * @param int                              $indent Left indent in twips.
	 * @return void
	 */
	public function add_blocks( array $blocks, int $indent = 0 ): void {
		foreach ( $blocks as $block ) {
			switch ( $block['type'] ) {
				case 'heading':
					$this->add_heading( $block );
					break;
				case 'paragraph':
					$this->body .= $this->paragraph( $this->runs_xml( $block['runs'] ), array( 'indent' => $indent ) );
					break;
				case 'image':
					$this->add_image( $block, $indent );
					break;
				case 'code':
					$this->add_code( $block, $indent );
					break;
				case 'list':
					$this->add_list( $block, $indent );
					break;
			}
		}
	}

	/**
	 * Serialises the package to disk.
	 *
	 * @param string $path   Destination .docx path.
	 * @param string $title  Document title for the file properties.
	 * @return void
	 */
	public function save( string $path, string $title ): void {
		$archive = new ZipArchive();

		if ( file_exists( $path ) ) {
			unlink( $path );
		}

		if ( true !== $archive->open( $path, ZipArchive::CREATE ) ) {
			throw new RuntimeException( sprintf( 'Unable to create %s.', $path ) );
		}

		$archive->addFromString( '[Content_Types].xml', $this->content_types_xml() );
		$archive->addFromString( '_rels/.rels', $this->package_rels_xml() );
		$archive->addFromString( 'docProps/core.xml', $this->core_properties_xml( $title ) );
		$archive->addFromString( 'word/document.xml', $this->document_xml() );
		$archive->addFromString( 'word/_rels/document.xml.rels', $this->document_rels_xml() );
		$archive->addFromString( 'word/styles.xml', $this->styles_xml() );
		$archive->addFromString( 'word/numbering.xml', $this->numbering_xml() );
		$archive->addFromString( 'word/settings.xml', $this->settings_xml() );

		foreach ( $this->images as $relationship_id => $source ) {
			$archive->addFile( $source, sprintf( 'word/media/%s.png', $relationship_id ) );
		}

		$archive->close();
	}

	/**
	 * Appends a heading paragraph.
	 *
	 * @param array<string, mixed> $block Heading block.
	 * @return void
	 */
	private function add_heading( array $block ): void {
		$level = min( 3, max( 1, (int) $block['level'] ) );
		$sizes = array(
			1 => 36,
			2 => 30,
			3 => 26,
		);

		$this->body .= $this->paragraph(
			$this->text_run(
				$block['text'],
				array( 'bold' => true, 'size' => $sizes[ $level ], 'colour' => self::BRAND_COLOUR )
			),
			array(
				'style'        => 'Heading' . $level,
				'space_before' => 1 === $level ? 400 : 280,
				'space_after'  => 120,
				'keep_next'    => true,
			)
		);
	}

	/**
	 * Appends an inline image paragraph.
	 *
	 * @param array<string, mixed> $block  Image block.
	 * @param int                  $indent Left indent in twips.
	 * @return void
	 */
	private function add_image( array $block, int $indent ): void {
		$source = $this->asset_root . '/' . $block['src'];

		if ( ! is_readable( $source ) ) {
			throw new RuntimeException( sprintf( 'Missing image %s.', $block['src'] ) );
		}

		$dimensions = getimagesize( $source );

		if ( false === $dimensions ) {
			throw new RuntimeException( sprintf( 'Unreadable image %s.', $block['src'] ) );
		}

		$available = self::CONTENT_WIDTH_EMU - ( $indent * 635 );
		$width     = $dimensions[0] * self::EMU_PER_PIXEL;
		$height    = $dimensions[1] * self::EMU_PER_PIXEL;

		if ( $width > $available ) {
			$height = (int) round( $height * ( $available / $width ) );
			$width  = $available;
		}

		$relationship_id = $this->add_relationship( 'image', $source );
		$drawing_id      = $this->next_drawing++;

		$drawing = sprintf(
			'<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
				. '<wp:extent cx="%1$d" cy="%2$d"/><wp:effectExtent l="0" t="0" r="0" b="0"/>'
				. '<wp:docPr id="%3$d" name="Picture %3$d" descr="%4$s"/>'
				. '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
				. '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
				. '<pic:pic><pic:nvPicPr><pic:cNvPr id="%3$d" name="%4$s"/><pic:cNvPicPr/></pic:nvPicPr>'
				. '<pic:blipFill><a:blip r:embed="%5$s"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
				. '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="%1$d" cy="%2$d"/></a:xfrm>'
				. '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
				. '</a:graphicData></a:graphic></wp:inline></w:drawing>',
			(int) $width,
			(int) $height,
			$drawing_id,
			self::escape( $block['alt'] ),
			$relationship_id
		);

		$this->body .= $this->paragraph(
			'<w:r>' . $drawing . '</w:r>',
			array( 'indent' => $indent, 'space_before' => 80, 'space_after' => 200 )
		);
	}

	/**
	 * Appends a shaded code block, one paragraph per line.
	 *
	 * @param array<string, mixed> $block  Code block.
	 * @param int                  $indent Left indent in twips.
	 * @return void
	 */
	private function add_code( array $block, int $indent ): void {
		$lines = explode( "\n", $block['text'] );

		foreach ( $lines as $position => $line ) {
			$this->body .= $this->paragraph(
				$this->text_run( $line, array( 'mono' => true, 'size' => 20 ) ),
				array(
					'indent'       => $indent + 240,
					'shading'      => 'F2F2F2',
					'space_before' => 0 === $position ? 120 : 0,
					'space_after'  => count( $lines ) - 1 === $position ? 200 : 0,
				)
			);
		}
	}

	/**
	 * Appends a numbered or bulleted list.
	 *
	 * @param array<string, mixed> $block  List block.
	 * @param int                  $indent Left indent in twips.
	 * @return void
	 */
	private function add_list( array $block, int $indent ): void {
		$number_id = self::BULLET_NUM_ID;

		if ( $block['ordered'] ) {
			$number_id             = count( $this->ordered_lists ) + 2;
			$this->ordered_lists[] = $number_id;
		}

		foreach ( $block['items'] as $item ) {
			$first = true;

			foreach ( $item as $item_block ) {
				if ( $first && 'paragraph' === $item_block['type'] ) {
					$this->body .= $this->paragraph(
						$this->runs_xml( $item_block['runs'] ),
						array(
							'style'       => 'ListParagraph',
							'number_id'   => $number_id,
							'indent'      => $indent + self::LIST_INDENT,
							'hanging'     => 360,
							'space_after' => 40,
						)
					);
					$first = false;
					continue;
				}

				$this->add_blocks( array( $item_block ), $indent + self::LIST_INDENT );
				$first = false;
			}
		}
	}

	/**
	 * Wraps runs in a paragraph.
	 *
	 * @param string               $runs    Run XML.
	 * @param array<string, mixed> $options Paragraph options.
	 * @return string
	 */
	private function paragraph( string $runs, array $options = array() ): string {
		// Word rejects a document whose pPr children are out of schema order.
		$properties = '';

		if ( isset( $options['style'] ) ) {
			$properties .= sprintf( '<w:pStyle w:val="%s"/>', $options['style'] );
		}

		if ( ! empty( $options['keep_next'] ) ) {
			$properties .= '<w:keepNext/><w:keepLines/>';
		}

		if ( isset( $options['number_id'] ) ) {
			$properties .= sprintf(
				'<w:numPr><w:ilvl w:val="0"/><w:numId w:val="%d"/></w:numPr>',
				$options['number_id']
			);
		}

		if ( isset( $options['shading'] ) ) {
			$properties .= sprintf( '<w:shd w:val="clear" w:color="auto" w:fill="%s"/>', $options['shading'] );
		}

		$properties .= sprintf(
			'<w:spacing w:before="%d" w:after="%d"/>',
			(int) ( $options['space_before'] ?? 0 ),
			(int) ( $options['space_after'] ?? 160 )
		);

		$indent = (int) ( $options['indent'] ?? 0 );

		if ( 0 !== $indent || isset( $options['hanging'] ) ) {
			$properties .= sprintf(
				'<w:ind w:left="%d"%s/>',
				$indent,
				isset( $options['hanging'] ) ? sprintf( ' w:hanging="%d"', $options['hanging'] ) : ''
			);
		}

		if ( isset( $options['align'] ) ) {
			$properties .= sprintf( '<w:jc w:val="%s"/>', $options['align'] );
		}

		return sprintf( '<w:p><w:pPr>%s</w:pPr>%s</w:p>', $properties, $runs );
	}

	/**
	 * Converts parsed runs to run XML.
	 *
	 * @param array<int, array<string, mixed>> $runs Parsed runs.
	 * @return string
	 */
	private function runs_xml( array $runs ): string {
		$xml = '';

		foreach ( $runs as $run ) {
			$formatting = array(
				'bold' => (bool) $run['bold'],
				'mono' => (bool) $run['code'],
			);

			if ( null === $run['href'] ) {
				$xml .= $this->text_run( $run['text'], $formatting );
				continue;
			}

			$formatting['colour']    = '0563C1';
			$formatting['underline'] = true;

			$xml .= sprintf(
				'<w:hyperlink r:id="%s">%s</w:hyperlink>',
				$this->add_relationship( 'hyperlink', $run['href'] ),
				$this->text_run( $run['text'], $formatting )
			);
		}

		return $xml;
	}

	/**
	 * Builds a single text run.
	 *
	 * @param string               $text       Run text.
	 * @param array<string, mixed> $formatting Run formatting.
	 * @return string
	 */
	private function text_run( string $text, array $formatting = array() ): string {
		$font       = ! empty( $formatting['mono'] ) ? 'Consolas' : 'Arial';
		$size       = (int) ( $formatting['size'] ?? ( ! empty( $formatting['mono'] ) ? 20 : 22 ) );
		$colour     = (string) ( $formatting['colour'] ?? self::TEXT_COLOUR );
		$properties = sprintf(
			'<w:rFonts w:ascii="%1$s" w:hAnsi="%1$s" w:cs="%1$s"/>',
			$font
		);

		if ( ! empty( $formatting['bold'] ) ) {
			$properties .= '<w:b/>';
		}

		$properties .= sprintf( '<w:color w:val="%s"/>', $colour );
		$properties .= sprintf( '<w:sz w:val="%1$d"/><w:szCs w:val="%1$d"/>', $size );

		if ( ! empty( $formatting['underline'] ) ) {
			$properties .= '<w:u w:val="single"/>';
		}

		if ( ! empty( $formatting['mono'] ) ) {
			$properties .= '<w:shd w:val="clear" w:color="auto" w:fill="F2F2F2"/>';
		}

		return sprintf(
			'<w:r><w:rPr>%s</w:rPr><w:t xml:space="preserve">%s</w:t></w:r>',
			$properties,
			self::escape( $text )
		);
	}

	/**
	 * Registers a relationship and returns its id.
	 *
	 * @param string $type   Relationship type, image or hyperlink.
	 * @param string $target Relationship target.
	 * @return string
	 */
	private function add_relationship( string $type, string $target ): string {
		if ( 'image' === $type ) {
			$existing = array_search( $target, $this->images, true );

			if ( false !== $existing ) {
				return (string) $existing;
			}
		}

		$relationship_id = 'rId' . $this->next_relationship++;

		if ( 'image' === $type ) {
			$this->images[ $relationship_id ] = $target;
		} else {
			$this->hyperlinks[ $relationship_id ] = $target;
		}

		return $relationship_id;
	}

	/**
	 * Builds word/document.xml.
	 *
	 * @return string
	 */
	private function document_xml(): string {
		return self::XML_DECLARATION
			. '<w:document '
			. 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
			. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
			. 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
			. 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
			. 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
			. '<w:body>' . $this->body
			. '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
			. '<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" '
			. 'w:header="708" w:footer="708" w:gutter="0"/>'
			. '<w:cols w:space="708"/><w:docGrid w:linePitch="360"/></w:sectPr>'
			. '</w:body></w:document>';
	}

	/**
	 * Builds word/_rels/document.xml.rels.
	 *
	 * @return string
	 */
	private function document_rels_xml(): string {
		$base          = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
		$relationships = sprintf(
			'<Relationship Id="rId1" Type="%1$sstyles" Target="styles.xml"/>'
				. '<Relationship Id="rId2" Type="%1$snumbering" Target="numbering.xml"/>'
				. '<Relationship Id="rId3" Type="%1$ssettings" Target="settings.xml"/>',
			$base
		);

		foreach ( $this->images as $relationship_id => $source ) {
			$relationships .= sprintf(
				'<Relationship Id="%1$s" Type="%2$simage" Target="media/%1$s.png"/>',
				$relationship_id,
				$base
			);
		}

		foreach ( $this->hyperlinks as $relationship_id => $target ) {
			$relationships .= sprintf(
				'<Relationship Id="%1$s" Type="%2$shyperlink" Target="%3$s" TargetMode="External"/>',
				$relationship_id,
				$base,
				self::escape( $target )
			);
		}

		return self::XML_DECLARATION
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. $relationships . '</Relationships>';
	}

	/**
	 * Builds [Content_Types].xml.
	 *
	 * @return string
	 */
	private function content_types_xml(): string {
		$word = 'application/vnd.openxmlformats-officedocument.wordprocessingml.';

		return self::XML_DECLARATION
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Default Extension="png" ContentType="image/png"/>'
			. sprintf( '<Override PartName="/word/document.xml" ContentType="%sdocument.main+xml"/>', $word )
			. sprintf( '<Override PartName="/word/styles.xml" ContentType="%sstyles+xml"/>', $word )
			. sprintf( '<Override PartName="/word/numbering.xml" ContentType="%snumbering+xml"/>', $word )
			. sprintf( '<Override PartName="/word/settings.xml" ContentType="%ssettings+xml"/>', $word )
			. '<Override PartName="/docProps/core.xml" '
			. 'ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			. '</Types>';
	}

	/**
	 * Builds _rels/.rels.
	 *
	 * @return string
	 */
	private function package_rels_xml(): string {
		$base = 'http://schemas.openxmlformats.org/';

		return self::XML_DECLARATION
			. '<Relationships xmlns="' . $base . 'package/2006/relationships">'
			. sprintf(
				'<Relationship Id="rId1" Type="%sofficeDocument/2006/relationships/officeDocument" '
					. 'Target="word/document.xml"/>',
				$base
			)
			. sprintf(
				'<Relationship Id="rId2" Type="%spackage/2006/relationships/metadata/core-properties" '
					. 'Target="docProps/core.xml"/>',
				$base
			)
			. '</Relationships>';
	}

	/**
	 * Builds docProps/core.xml.
	 *
	 * @param string $title Document title.
	 * @return string
	 */
	private function core_properties_xml( string $title ): string {
		$stamp = gmdate( 'Y-m-d\TH:i:s\Z' );

		return self::XML_DECLARATION
			. '<cp:coreProperties '
			. 'xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
			. 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
			. 'xmlns:dcterms="http://purl.org/dc/terms/" '
			. 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
			. sprintf( '<dc:title>%s</dc:title>', self::escape( $title ) )
			. '<dc:creator>Bank of the Cook Islands</dc:creator>'
			. '<cp:lastModifiedBy>Bank of the Cook Islands</cp:lastModifiedBy>'
			. sprintf( '<dcterms:created xsi:type="dcterms:W3CDTF">%s</dcterms:created>', $stamp )
			. sprintf( '<dcterms:modified xsi:type="dcterms:W3CDTF">%s</dcterms:modified>', $stamp )
			. '</cp:coreProperties>';
	}

	/**
	 * Builds word/styles.xml.
	 *
	 * @return string
	 */
	private function styles_xml(): string {
		$headings = '';

		foreach ( array( 1, 2, 3 ) as $level ) {
			$headings .= sprintf(
				'<w:style w:type="paragraph" w:styleId="Heading%1$d"><w:name w:val="heading %1$d"/>'
					. '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
					. '<w:pPr><w:keepNext/><w:keepLines/><w:outlineLvl w:val="%2$d"/></w:pPr>'
					. '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:b/>'
					. '<w:color w:val="%3$s"/><w:sz w:val="%4$d"/></w:rPr></w:style>',
				$level,
				$level - 1,
				self::BRAND_COLOUR,
				array( 1 => 36, 2 => 30, 3 => 26 )[ $level ]
			);
		}

		return self::XML_DECLARATION
			. '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
			. '<w:docDefaults><w:rPrDefault><w:rPr>'
			. '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
			. sprintf( '<w:color w:val="%s"/>', self::TEXT_COLOUR )
			. '<w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="en-NZ"/>'
			. '</w:rPr></w:rPrDefault>'
			. '<w:pPrDefault><w:pPr><w:spacing w:after="160" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
			. '</w:docDefaults>'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/>'
			. '<w:qFormat/></w:style>'
			. '<w:style w:type="character" w:default="1" w:styleId="DefaultParagraphFont">'
			. '<w:name w:val="Default Paragraph Font"/></w:style>'
			. $headings
			. '<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/>'
			. '<w:basedOn w:val="Normal"/><w:qFormat/>'
			. '<w:pPr><w:ind w:left="720"/><w:contextualSpacing/></w:pPr></w:style>'
			. '</w:styles>';
	}

	/**
	 * Builds word/numbering.xml.
	 *
	 * Each ordered list gets its own numbering instance so step numbers restart
	 * at one in every section.
	 *
	 * @return string
	 */
	private function numbering_xml(): string {
		$bullet_levels  = '';
		$decimal_levels = '';
		$bullets        = array( "\u{F0B7}", 'o', "\u{F0A7}" );
		$fonts          = array( 'Symbol', 'Courier New', 'Wingdings' );
		$formats        = array( 'decimal', 'lowerLetter', 'lowerRoman' );

		for ( $level = 0; $level < 9; ++$level ) {
			$indent = 720 * ( $level + 1 );

			$bullet_levels .= sprintf(
				'<w:lvl w:ilvl="%1$d"><w:start w:val="1"/><w:numFmt w:val="bullet"/>'
					. '<w:lvlText w:val="%2$s"/><w:lvlJc w:val="left"/>'
					. '<w:pPr><w:ind w:left="%3$d" w:hanging="360"/></w:pPr>'
					. '<w:rPr><w:rFonts w:ascii="%4$s" w:hAnsi="%4$s" w:hint="default"/></w:rPr></w:lvl>',
				$level,
				self::escape( $bullets[ $level % 3 ] ),
				$indent,
				$fonts[ $level % 3 ]
			);

			$decimal_levels .= sprintf(
				'<w:lvl w:ilvl="%1$d"><w:start w:val="1"/><w:numFmt w:val="%2$s"/>'
					. '<w:lvlText w:val="%%%3$d."/><w:lvlJc w:val="left"/>'
					. '<w:pPr><w:ind w:left="%4$d" w:hanging="360"/></w:pPr></w:lvl>',
				$level,
				$formats[ $level % 3 ],
				$level + 1,
				$indent
			);
		}

		$instances = sprintf(
			'<w:num w:numId="%d"><w:abstractNumId w:val="0"/></w:num>',
			self::BULLET_NUM_ID
		);

		foreach ( $this->ordered_lists as $number_id ) {
			$instances .= sprintf(
				'<w:num w:numId="%d"><w:abstractNumId w:val="1"/>'
					. '<w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>',
				$number_id
			);
		}

		return self::XML_DECLARATION
			. '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
			. '<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="hybridMultilevel"/>'
			. $bullet_levels . '</w:abstractNum>'
			. '<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="hybridMultilevel"/>'
			. $decimal_levels . '</w:abstractNum>'
			. $instances
			. '</w:numbering>';
	}

	/**
	 * Builds word/settings.xml.
	 *
	 * @return string
	 */
	private function settings_xml(): string {
		return self::XML_DECLARATION
			. '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
			. '<w:defaultTabStop w:val="720"/><w:characterSpacingControl w:val="doNotCompress"/>'
			. '</w:settings>';
	}

	/**
	 * Escapes text for XML content.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function escape( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	private const XML_DECLARATION = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
}
/**
 * Measures text in the PDF base fourteen fonts.
 *
 * Widths are Adobe AFM values in thousandths of the font size, covering the
 * printable WinAnsi range the guide uses.
 */
final class Font_Metrics {

	public const REGULAR = 'F1';
	public const BOLD    = 'F2';
	public const MONO    = 'F3';

	/**
	 * Helvetica widths for character codes 32 to 126.
	 */
	private const HELVETICA = array(
		278, 278, 355, 556, 556, 889, 667, 191, 333, 333,
		389, 584, 278, 333, 278, 278, 556, 556, 556, 556,
		556, 556, 556, 556, 556, 556, 278, 278, 584, 584,
		584, 556, 1015, 667, 667, 722, 722, 667, 611, 778,
		722, 278, 500, 667, 556, 833, 722, 778, 667, 778,
		722, 667, 611, 722, 667, 944, 667, 667, 611, 278,
		278, 278, 469, 556, 333, 556, 556, 500, 556, 556,
		278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
		556, 556, 333, 500, 278, 556, 500, 722, 500, 500,
		500, 334, 260, 334, 584,
	);

	/**
	 * Helvetica-Bold widths for character codes 32 to 126.
	 */
	private const HELVETICA_BOLD = array(
		278, 333, 474, 556, 556, 889, 722, 238, 333, 333,
		389, 584, 278, 333, 278, 278, 556, 556, 556, 556,
		556, 556, 556, 556, 556, 556, 333, 333, 584, 584,
		584, 611, 975, 722, 722, 722, 722, 667, 611, 778,
		722, 278, 556, 722, 611, 833, 722, 778, 667, 778,
		722, 667, 611, 722, 667, 944, 667, 667, 611, 333,
		278, 333, 584, 556, 333, 556, 611, 556, 611, 556,
		333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
		611, 611, 389, 556, 333, 611, 556, 778, 556, 556,
		500, 389, 280, 389, 584,
	);

	private const COURIER_WIDTH = 600;

	/**
	 * Measures a string.
	 *
	 * @param string $text Text encoded in WinAnsi.
	 * @param string $font Font key.
	 * @param float  $size Font size in points.
	 * @return float
	 */
	public static function width( string $text, string $font, float $size ): float {
		$total = 0;
		$table = self::BOLD === $font ? self::HELVETICA_BOLD : self::HELVETICA;

		for ( $position = 0; $position < strlen( $text ); ++$position ) {
			if ( self::MONO === $font ) {
				$total += self::COURIER_WIDTH;
				continue;
			}

			$code   = ord( $text[ $position ] );
			$total += $table[ $code - 32 ] ?? 556;
		}

		return $total * $size / 1000;
	}
}

/**
 * Lays blocks out on A4 pages and writes a PDF.
 *
 * The writer produces the file directly rather than shelling out to a browser,
 * so a release build needs nothing beyond PHP.
 */
final class Pdf_Writer {

	private const PAGE_WIDTH    = 595.28;
	private const PAGE_HEIGHT   = 841.89;
	private const MARGIN_SIDE   = 70.9;
	private const MARGIN_TOP    = 70.9;
	private const MARGIN_BOTTOM = 62.4;

	private const BODY_SIZE    = 10.5;
	private const BODY_LEADING = 14.5;
	private const CODE_SIZE    = 9.5;
	private const LIST_INDENT  = 20.0;

	private const BRAND    = array( 0, 0.631, 0.698 );
	private const TEXT     = array( 0.14, 0.14, 0.14 );
	private const MUTED    = array( 0.35, 0.35, 0.35 );
	private const LINK     = array( 0.02, 0.39, 0.76 );
	private const SHADING  = array( 0.949, 0.949, 0.949 );
	private const HAIRLINE = array( 0.85, 0.85, 0.85 );

	/**
	 * Content stream fragments, one entry per page.
	 *
	 * @var array<int, string>
	 */
	private array $pages = array( '' );

	/**
	 * Link annotations per page, as [rect, url].
	 *
	 * @var array<int, array<int, array{0: array<int, float>, 1: string}>>
	 */
	private array $annotations = array( 0 => array() );

	/**
	 * Flattened image streams keyed by resource name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $images = array();

	/**
	 * Source path to resource name, so a repeated screenshot is embedded once.
	 *
	 * @var array<string, string>
	 */
	private array $image_names = array();

	private int $page = 0;
	private float $cursor;

	/**
	 * Directory the markdown image paths are relative to.
	 *
	 * @var string
	 */
	private string $asset_root;

	/**
	 * @param string $asset_root Directory the markdown image paths resolve against.
	 */
	public function __construct( string $asset_root ) {
		$this->asset_root = $asset_root;
		$this->cursor     = self::PAGE_HEIGHT - self::MARGIN_TOP;
	}

	/**
	 * Writes the centred cover block.
	 *
	 * @param string $title    Document title.
	 * @param string $subtitle Subtitle line.
	 * @return void
	 */
	public function add_cover( string $title, string $subtitle ): void {
		$this->cursor -= 10;
		$this->draw_centred( $title, Font_Metrics::BOLD, 19, self::BRAND );
		$this->cursor -= 24;
		$this->draw_centred( $subtitle, Font_Metrics::REGULAR, 10.5, self::MUTED );
		$this->cursor -= 30;
	}

	/**
	 * Lays out a list of blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks to lay out.
	 * @param float                            $indent Left indent in points.
	 * @return void
	 */
	public function add_blocks( array $blocks, float $indent = 0 ): void {
		foreach ( $blocks as $block ) {
			switch ( $block['type'] ) {
				case 'heading':
					$this->add_heading( $block );
					break;
				case 'paragraph':
					$this->add_paragraph( $block['runs'], $indent );
					$this->cursor -= 6;
					break;
				case 'image':
					$this->add_image( $block, $indent );
					break;
				case 'code':
					$this->add_code( $block, $indent );
					break;
				case 'list':
					$this->add_list( $block, $indent );
					break;
			}
		}
	}

	/**
	 * Serialises the document.
	 *
	 * @param string $path  Destination path.
	 * @param string $title Document title for the metadata.
	 * @return void
	 */
	public function save( string $path, string $title ): void {
		$objects    = array();
		$page_count = count( $this->pages );
		$first_page = 4 + count( $this->images );

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = sprintf(
			'<< /Type /Pages /Count %d /Kids [%s] >>',
			$page_count,
			implode(
				' ',
				array_map(
					static fn( int $index ): string => sprintf( '%d 0 R', $first_page + $index * 2 ),
					range( 0, $page_count - 1 )
				)
			)
		);
		$objects[3] = sprintf(
			'<< /Type /Info /Title (%s) /Author (Bank of the Cook Islands) /Producer (BCI docs build) >>',
			self::escape_string( self::encode( $title ) )
		);

		$resources     = array();
		$next_object   = 4;

		foreach ( $this->images as $name => $image ) {
			$objects[ $next_object ] = array(
				'dictionary' => sprintf(
					'<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB '
						. '/BitsPerComponent 8 /Filter /FlateDecode '
						. '/DecodeParms << /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns %d >> '
						. '/Length %d >>',
					$image['width'],
					$image['height'],
					$image['width'],
					strlen( $image['stream'] )
				),
				'stream'     => $image['stream'],
			);
			$resources[ $name ] = sprintf( '/%s %d 0 R', $name, $next_object );
			++$next_object;
		}

		$fonts = sprintf(
			'/Font << /F1 %1$d 0 R /F2 %2$d 0 R /F3 %3$d 0 R >>',
			$first_page + $page_count * 2,
			$first_page + $page_count * 2 + 1,
			$first_page + $page_count * 2 + 2
		);

		$xobjects = array() === $resources ? '' : sprintf( ' /XObject << %s >>', implode( ' ', $resources ) );

		foreach ( $this->pages as $index => $content ) {
			$object_id            = $first_page + $index * 2;
			$stream               = gzcompress( $content, 9 );
			$objects[ $object_id ] = sprintf(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
					. '/Resources << %s%s >> /Contents %d 0 R%s >>',
				self::PAGE_WIDTH,
				self::PAGE_HEIGHT,
				$fonts,
				$xobjects,
				$object_id + 1,
				$this->annotations_for_page( $index )
			);
			$objects[ $object_id + 1 ] = array(
				'dictionary' => sprintf( '<< /Filter /FlateDecode /Length %d >>', strlen( $stream ) ),
				'stream'     => $stream,
			);
		}

		$font_base = $first_page + $page_count * 2;

		foreach ( array( 'Helvetica', 'Helvetica-Bold', 'Courier' ) as $offset => $base_font ) {
			$objects[ $font_base + $offset ] = sprintf(
				'<< /Type /Font /Subtype /Type1 /BaseFont /%s /Encoding /WinAnsiEncoding >>',
				$base_font
			);
		}

		file_put_contents( $path, $this->serialise( $objects ) );
	}

	/**
	 * Adds a heading, keeping it with the text that follows.
	 *
	 * @param array<string, mixed> $block Heading block.
	 * @return void
	 */
	private function add_heading( array $block ): void {
		$level = min( 3, max( 2, (int) $block['level'] ) );
		$size  = 2 === $level ? 14.5 : 12;

		$this->cursor -= 2 === $level ? 16 : 12;
		$this->require_space( $size + 46 );
		$this->draw_text_line(
			self::MARGIN_SIDE,
			$this->cursor - $size,
			self::encode( $block['text'] ),
			Font_Metrics::BOLD,
			$size,
			self::BRAND
		);
		$this->cursor -= $size + 7;
	}

	/**
	 * Adds a wrapped paragraph.
	 *
	 * @param array<int, array<string, mixed>> $runs   Parsed runs.
	 * @param float                            $indent Left indent in points.
	 * @return void
	 */
	private function add_paragraph( array $runs, float $indent ): void {
		$width = self::PAGE_WIDTH - 2 * self::MARGIN_SIDE - $indent;

		foreach ( $this->wrap( $runs, $width ) as $line ) {
			$this->require_space( self::BODY_LEADING );
			$this->draw_line( $line, self::MARGIN_SIDE + $indent );
			$this->cursor -= self::BODY_LEADING;
		}
	}

	/**
	 * Adds a numbered or bulleted list.
	 *
	 * @param array<string, mixed> $block  List block.
	 * @param float                $indent Left indent in points.
	 * @return void
	 */
	private function add_list( array $block, float $indent ): void {
		$number = 1;

		foreach ( $block['items'] as $item ) {
			$first = true;

			foreach ( $item as $item_block ) {
				if ( $first && 'paragraph' === $item_block['type'] ) {
					$this->require_space( self::BODY_LEADING );
					$marker = $block['ordered'] ? $number . '.' : self::encode( '•' );
					$this->draw_text_line(
						self::MARGIN_SIDE + $indent,
						$this->cursor - self::BODY_SIZE,
						$marker,
						Font_Metrics::REGULAR,
						self::BODY_SIZE,
						self::TEXT
					);
					$this->add_paragraph( $item_block['runs'], $indent + self::LIST_INDENT );
					$first = false;
					continue;
				}

				$this->add_blocks( array( $item_block ), $indent + self::LIST_INDENT );
				$first = false;
			}

			$this->cursor -= 3;
			++$number;
		}

		$this->cursor -= 6;
	}

	/**
	 * Adds a shaded code block.
	 *
	 * @param array<string, mixed> $block  Code block.
	 * @param float                $indent Left indent in points.
	 * @return void
	 */
	private function add_code( array $block, float $indent ): void {
		$lines  = explode( "\n", $block['text'] );
		$height = count( $lines ) * 13 + 12;

		$this->require_space( $height + 6 );
		$this->draw_rectangle(
			self::MARGIN_SIDE + $indent,
			$this->cursor - $height,
			self::PAGE_WIDTH - 2 * self::MARGIN_SIDE - $indent,
			$height,
			self::SHADING
		);

		$this->cursor -= 6;

		foreach ( $lines as $line ) {
			$this->draw_text_line(
				self::MARGIN_SIDE + $indent + 8,
				$this->cursor - self::CODE_SIZE - 2,
				self::encode( $line ),
				Font_Metrics::MONO,
				self::CODE_SIZE,
				self::TEXT
			);
			$this->cursor -= 13;
		}

		$this->cursor -= 16;
	}

	/**
	 * Places a screenshot, moving it to the next page when it will not fit.
	 *
	 * @param array<string, mixed> $block  Image block.
	 * @param float                $indent Left indent in points.
	 * @return void
	 */
	private function add_image( array $block, float $indent ): void {
		$name      = $this->register_image( $this->asset_root . '/' . $block['src'] );
		$image     = $this->images[ $name ];
		$available = self::PAGE_WIDTH - 2 * self::MARGIN_SIDE - $indent;
		$width     = $image['width'] * 0.75;
		$height    = $image['height'] * 0.75;

		if ( $width > $available ) {
			$height = $height * ( $available / $width );
			$width  = $available;
		}

		$page_height = self::PAGE_HEIGHT - self::MARGIN_TOP - self::MARGIN_BOTTOM;

		if ( $height > $page_height ) {
			$width  = $width * ( $page_height / $height );
			$height = $page_height;
		}

		$this->cursor -= 4;
		$this->require_space( $height + 8 );

		$left = self::MARGIN_SIDE + $indent;
		$this->append(
			sprintf(
				"q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
				$width,
				$height,
				$left,
				$this->cursor - $height,
				$name
			)
		);
		$this->draw_rectangle( $left, $this->cursor - $height, $width, $height, self::HAIRLINE, false );
		$this->cursor -= $height + 10;
	}

	/**
	 * Flattens a PNG onto white and stores its compressed scanlines.
	 *
	 * @param string $source Absolute image path.
	 * @return string Resource name.
	 */
	private function register_image( string $source ): string {
		if ( isset( $this->image_names[ $source ] ) ) {
			return $this->image_names[ $source ];
		}

		if ( ! is_readable( $source ) ) {
			throw new RuntimeException( sprintf( 'Missing image %s.', $source ) );
		}

		$original = imagecreatefrompng( $source );

		if ( false === $original ) {
			throw new RuntimeException( sprintf( 'Unreadable image %s.', $source ) );
		}

		$width  = imagesx( $original );
		$height = imagesy( $original );
		$canvas = imagecreatetruecolor( $width, $height );
		imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 255, 255, 255 ) );
		imagealphablending( $canvas, true );
		imagecopy( $canvas, $original, 0, 0, 0, 0, $width, $height );
		imagesavealpha( $canvas, false );

		ob_start();
		imagepng( $canvas, null, 9 );
		$flattened = (string) ob_get_clean();

		imagedestroy( $original );
		imagedestroy( $canvas );

		$name                       = 'Im' . ( count( $this->images ) + 1 );
		$this->images[ $name ]      = array(
			'width'  => $width,
			'height' => $height,
			'stream' => self::png_data( $flattened ),
		);
		$this->image_names[ $source ] = $name;

		return $name;
	}

	/**
	 * Extracts the compressed scanlines from a PNG.
	 *
	 * GD writes eight bit truecolour, so the deflate stream is already in the
	 * layout a PDF image with a PNG predictor expects.
	 *
	 * @param string $png Raw PNG bytes.
	 * @return string
	 */
	private static function png_data( string $png ): string {
		$offset = 8;
		$data   = '';

		while ( $offset < strlen( $png ) ) {
			$length = unpack( 'N', substr( $png, $offset, 4 ) )[1];
			$type   = substr( $png, $offset + 4, 4 );

			if ( 'IDAT' === $type ) {
				$data .= substr( $png, $offset + 8, $length );
			}

			$offset += $length + 12;
		}

		return $data;
	}

	/**
	 * Breaks runs into lines that fit the given width.
	 *
	 * @param array<int, array<string, mixed>> $runs  Parsed runs.
	 * @param float                            $width Available width in points.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private function wrap( array $runs, float $width ): array {
		$lines   = array();
		$current = array();
		$used    = 0.0;

		foreach ( self::words( $runs ) as $word ) {
			$word_width = 0.0;

			foreach ( $word as $segment ) {
				$word_width += Font_Metrics::width( $segment['text'], $segment['font'], $segment['size'] );
			}

			// Always a body space, so a monospaced word does not widen the gap
			// that follows it.
			$space = array() === $current ? 0.0 : Font_Metrics::width( ' ', Font_Metrics::REGULAR, self::BODY_SIZE );

			if ( array() !== $current && $used + $space + $word_width > $width ) {
				$lines[] = $current;
				$current = array();
				$space   = 0.0;
				$used    = 0.0;
			}

			if ( 0.0 < $space ) {
				$current[ count( $current ) - 1 ]['space_after'] = $space;
				$used                                           += $space;
			}

			foreach ( $word as $segment ) {
				$current[] = $segment;
			}

			$used += $word_width;
		}

		if ( array() !== $current ) {
			$lines[] = $current;
		}

		return $lines;
	}

	/**
	 * Splits runs into words, keeping per character styling intact.
	 *
	 * @param array<int, array<string, mixed>> $runs Parsed runs.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private static function words( array $runs ): array {
		$words   = array();
		$current = array();

		foreach ( $runs as $run ) {
			$font = $run['code'] ? Font_Metrics::MONO : ( $run['bold'] ? Font_Metrics::BOLD : Font_Metrics::REGULAR );
			$size = $run['code'] ? self::CODE_SIZE : self::BODY_SIZE;
			$text = self::encode( $run['text'] );

			foreach ( explode( ' ', $text ) as $position => $piece ) {
				if ( 0 !== $position ) {
					if ( array() !== $current ) {
						$words[] = $current;
					}

					$current = array();
				}

				if ( '' === $piece ) {
					continue;
				}

				$current[] = array(
					'text'        => $piece,
					'font'        => $font,
					'size'        => $size,
					'colour'      => null === $run['href'] ? self::TEXT : self::LINK,
					'href'        => $run['href'],
					'shaded'      => (bool) $run['code'],
					'space_after' => 0.0,
				);
			}
		}

		if ( array() !== $current ) {
			$words[] = $current;
		}

		return $words;
	}

	/**
	 * Draws one wrapped line.
	 *
	 * @param array<int, array<string, mixed>> $segments Line segments.
	 * @param float                            $left     Left edge in points.
	 * @return void
	 */
	private function draw_line( array $segments, float $left ): void {
		$x        = $left;
		$baseline = $this->cursor - self::BODY_SIZE;

		foreach ( $segments as $segment ) {
			$width = Font_Metrics::width( $segment['text'], $segment['font'], $segment['size'] );

			if ( $segment['shaded'] ) {
				$this->draw_rectangle( $x - 1, $baseline - 2.5, $width + 2, self::BODY_LEADING - 3, self::SHADING );
			}

			$this->draw_text_line( $x, $baseline, $segment['text'], $segment['font'], $segment['size'], $segment['colour'] );

			if ( null !== $segment['href'] ) {
				$this->draw_rectangle( $x, $baseline - 1.5, $width, 0.5, $segment['colour'] );
				$this->annotations[ $this->page ][] = array(
					array( $x, $baseline - 2, $x + $width, $baseline + $segment['size'] ),
					$segment['href'],
				);
			}

			$x += $width + $segment['space_after'];
		}
	}

	/**
	 * Draws a horizontally centred line.
	 *
	 * @param string           $text   Line text.
	 * @param string           $font   Font key.
	 * @param float            $size   Font size.
	 * @param array<int, float> $colour Fill colour.
	 * @return void
	 */
	private function draw_centred( string $text, string $font, float $size, array $colour ): void {
		$encoded = self::encode( $text );
		$width   = Font_Metrics::width( $encoded, $font, $size );

		$this->draw_text_line(
			( self::PAGE_WIDTH - $width ) / 2,
			$this->cursor - $size,
			$encoded,
			$font,
			$size,
			$colour
		);
	}

	/**
	 * Emits a text showing operator.
	 *
	 * @param float             $x      Left edge.
	 * @param float             $y      Baseline.
	 * @param string            $text   WinAnsi text.
	 * @param string            $font   Font key.
	 * @param float             $size   Font size.
	 * @param array<int, float> $colour Fill colour.
	 * @return void
	 */
	private function draw_text_line( float $x, float $y, string $text, string $font, float $size, array $colour ): void {
		if ( '' === $text ) {
			return;
		}

		$this->append(
			sprintf(
				"BT %.3F %.3F %.3F rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
				$colour[0],
				$colour[1],
				$colour[2],
				$font,
				$size,
				$x,
				$y,
				self::escape_string( $text )
			)
		);
	}

	/**
	 * Emits a filled or stroked rectangle.
	 *
	 * @param float             $x      Left edge.
	 * @param float             $y      Bottom edge.
	 * @param float             $width  Width in points.
	 * @param float             $height Height in points.
	 * @param array<int, float> $colour Colour.
	 * @param bool              $fill   Whether to fill rather than stroke.
	 * @return void
	 */
	private function draw_rectangle( float $x, float $y, float $width, float $height, array $colour, bool $fill = true ): void {
		$this->append(
			sprintf(
				"q %.3F %.3F %.3F %s %.2F %.2F %.2F %.2F re %s Q\n",
				$colour[0],
				$colour[1],
				$colour[2],
				$fill ? 'rg' : 'RG',
				$x,
				$y,
				$width,
				$height,
				$fill ? 'f' : 'S'
			)
		);
	}

	/**
	 * Appends operators to the current page.
	 *
	 * @param string $operators Content stream fragment.
	 * @return void
	 */
	private function append( string $operators ): void {
		$this->pages[ $this->page ] .= $operators;
	}

	/**
	 * Starts a new page when the requested height will not fit.
	 *
	 * @param float $height Height needed in points.
	 * @return void
	 */
	private function require_space( float $height ): void {
		if ( $this->cursor - $height >= self::MARGIN_BOTTOM ) {
			return;
		}

		$this->pages[]       = '';
		$this->page          = count( $this->pages ) - 1;
		$this->annotations[ $this->page ] = array();
		$this->cursor        = self::PAGE_HEIGHT - self::MARGIN_TOP;
	}

	/**
	 * Builds the annotation entry for a page.
	 *
	 * @param int $index Page index.
	 * @return string
	 */
	private function annotations_for_page( int $index ): string {
		if ( array() === ( $this->annotations[ $index ] ?? array() ) ) {
			return '';
		}

		$entries = array();

		foreach ( $this->annotations[ $index ] as $annotation ) {
			$entries[] = sprintf(
				'<< /Type /Annot /Subtype /Link /Border [0 0 0] /Rect [%.2F %.2F %.2F %.2F] '
					. '/A << /S /URI /URI (%s) >> >>',
				$annotation[0][0],
				$annotation[0][1],
				$annotation[0][2],
				$annotation[0][3],
				self::escape_string( self::encode( $annotation[1] ) )
			);
		}

		return sprintf( ' /Annots [%s]', implode( ' ', $entries ) );
	}

	/**
	 * Assembles the objects into a PDF file with a cross reference table.
	 *
	 * @param array<int, string|array<string, string>> $objects Objects keyed by number.
	 * @return string
	 */
	private function serialise( array $objects ): string {
		ksort( $objects );
		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();

		foreach ( $objects as $number => $object ) {
			$offsets[ $number ] = strlen( $pdf );
			$pdf               .= sprintf( "%d 0 obj\n", $number );

			if ( is_array( $object ) ) {
				$pdf .= $object['dictionary'] . "\nstream\n" . $object['stream'] . "\nendstream\n";
			} else {
				$pdf .= $object . "\n";
			}

			$pdf .= "endobj\n";
		}

		$size  = max( array_keys( $offsets ) ) + 1;
		$start = strlen( $pdf );
		$pdf  .= sprintf( "xref\n0 %d\n0000000000 65535 f \n", $size );

		for ( $number = 1; $number < $size; ++$number ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $number ] ?? 0 );
		}

		$pdf .= sprintf(
			"trailer\n<< /Size %d /Root 1 0 R /Info 3 0 R >>\nstartxref\n%d\n%%%%EOF\n",
			$size,
			$start
		);

		return $pdf;
	}

	/**
	 * Converts UTF-8 text to the WinAnsi encoding the fonts declare.
	 *
	 * @param string $text UTF-8 text.
	 * @return string
	 */
	private static function encode( string $text ): string {
		return (string) iconv( 'UTF-8', 'CP1252//TRANSLIT', $text );
	}

	/**
	 * Escapes a PDF literal string.
	 *
	 * @param string $text WinAnsi text.
	 * @return string
	 */
	private static function escape_string( string $text ): string {
		return str_replace( array( '\\', '(', ')', "\r" ), array( '\\\\', '\\(', '\\)', '' ), $text );
	}
}
$options       = getopt( '', array( 'skip-pdf', 'skip-docx' ) );
$repository    = dirname( __DIR__ );
$markdown_path = $repository . '/docs/merchant-setup-guide.md';
$plugin_path   = $repository . '/woocommerce-gateway-bci.php';
$docx_path     = $repository . '/docs/Merchant Setup Guide.docx';
$pdf_path      = $repository . '/docs/Merchant Setup Guide.pdf';

$markdown = file_get_contents( $markdown_path );

if ( false === $markdown ) {
	fwrite( STDERR, sprintf( "Unable to read %s.\n", $markdown_path ) );
	exit( 1 );
}

preg_match( '/^\s*\*\s*Version:\s*(.+)$/m', (string) file_get_contents( $plugin_path ), $version_match );
$version = trim( $version_match[1] ?? '' );

if ( '' === $version ) {
	fwrite( STDERR, sprintf( "No plugin version found in %s.\n", $plugin_path ) );
	exit( 1 );
}

$blocks   = Guide_Markdown::parse( $markdown );
$title    = 'TakuEcom - BCI Payments for WooCommerce';
$subtitle = sprintf( 'Merchant Setup Guide | plugin v%s', $version );

if ( array() !== $blocks && 'heading' === $blocks[0]['type'] && 1 === $blocks[0]['level'] ) {
	$title = $blocks[0]['text'];
	array_shift( $blocks );
}

$asset_root = dirname( $markdown_path );

try {
	if ( ! isset( $options['skip-docx'] ) ) {
		$docx = new Docx_Writer( $asset_root );
		$docx->add_cover( $title, $subtitle );
		$docx->add_blocks( $blocks );
		$docx->save( $docx_path, $title );
		printf( "Wrote %s (%s bytes).\n", $docx_path, number_format( (int) filesize( $docx_path ) ) );
	}

	if ( ! isset( $options['skip-pdf'] ) ) {
		$pdf = new Pdf_Writer( $asset_root );
		$pdf->add_cover( $title, $subtitle );
		$pdf->add_blocks( $blocks );
		$pdf->save( $pdf_path, $title );
		printf( "Wrote %s (%s bytes).\n", $pdf_path, number_format( (int) filesize( $pdf_path ) ) );
	}
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
