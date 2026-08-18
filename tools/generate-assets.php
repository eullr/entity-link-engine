<?php
/**
 * Generate plugin assets (icon + banners) with GD.
 * Run: php assets/generate-assets.php
 */

$out = __DIR__ . '/../assets';
@mkdir( $out, 0755, true );

// ---- Shared palette -------------------------------------------------------
$bg      = array( 13, 24, 40 );     // deep navy
$bg2     = array( 22, 42, 68 );
$accent  = array( 72, 187, 255 );   // bright blue
$accent2 = array( 173, 255, 214 );  // mint
$line    = array( 120, 160, 200 );
$text    = array( 235, 245, 255 );

/**
 * Rounded rectangle.
 */
function round_rect( $im, $x1, $y1, $x2, $y2, $r, $color ) {
	$w = $x2 - $x1;
	$h = $y2 - $y1;
	imagefilledrectangle( $im, $x1 + $r, $y1, $x2 - $r, $y2, $color );
	imagefilledrectangle( $im, $x1, $y1 + $r, $x2, $y2 - $r, $color );
	imagefilledarc( $im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE );
	imagefilledarc( $im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE );
	imagefilledarc( $im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE );
	imagefilledarc( $im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE );
}

/**
 * Draw the link-node motif (three nodes + edges) scaled into a canvas.
 */
function draw_motif( $im, $cx, $cy, $scale, $accent, $accent2, $line ) {
	$nodes = array(
		array( -1.4, -0.6, 0.16 ),
		array( 0.0, 1.0, 0.13 ),
		array( 1.4, -0.6, 0.16 ),
	);
	$pts = array();
	foreach ( $nodes as $n ) {
		$pts[] = array( $cx + $n[0] * $scale, $cy + $n[1] * $scale, $n[2] * $scale );
	}
	// Edges.
	$edge = imagecolorallocate( $im, $line[0], $line[1], $line[2] );
	imagesetthickness( $im, max( 2, (int) ( $scale * 0.09 ) ) );
	imageline( $im, (int) $pts[0][0], (int) $pts[0][1], (int) $pts[1][0], (int) $pts[1][1], $edge );
	imageline( $im, (int) $pts[1][0], (int) $pts[1][1], (int) $pts[2][0], (int) $pts[2][1], $edge );
	imagesetthickness( $im, 1 );
	// Nodes.
	$c1 = imagecolorallocate( $im, $accent[0], $accent[1], $accent[2] );
	$c2 = imagecolorallocate( $im, $accent2[0], $accent2[1], $accent2[2] );
	$colors = array( $c1, $c2, $c1 );
	foreach ( $pts as $i => $p ) {
		imagefilledellipse( $im, (int) $p[0], (int) $p[1], (int) ( $p[2] * 2 ), (int) ( $p[2] * 2 ), $colors[ $i ] );
	}
}

/**
 * Render a full asset canvas.
 */
function render( $w, $h, $bg, $bg2, $accent, $accent2, $line, $text, $draw_motif_only ) {
	$im = imagecreatetruecolor( $w, $h );
	// Diagonal gradient.
	for ( $y = 0; $y < $h; $y++ ) {
		$t = $y / max( 1, $h - 1 );
		$r = (int) ( $bg[0] + ( $bg2[0] - $bg[0] ) * $t );
		$g = (int) ( $bg[1] + ( $bg2[1] - $bg[1] ) * $t );
		$b = (int) ( $bg[2] + ( $bg2[2] - $bg[2] ) * $t );
		imageline( $im, 0, $y, $w, $y, imagecolorallocate( $im, $r, $g, $b ) );
	}
	$scale = min( $w, $h ) * 0.34;
	if ( $draw_motif_only ) {
		draw_motif( $im, $w / 2, $h / 2, $scale, $accent, $accent2, $line );
	} else {
		draw_motif( $im, $w * 0.30, $h * 0.52, $scale * 0.9, $accent, $accent2, $line );
		$tcolor = imagecolorallocate( $im, $text[0], $text[1], $text[2] );
		$sub    = imagecolorallocate( $im, 160, 200, 230 );
		$font   = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
		$font2  = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( file_exists( $font ) ) {
			$size = max( 18, (int) ( $h * 0.16 ) );
			$box  = imagettfbbox( $size, 0, $font, 'Entity Link Engine' );
			$tw   = abs( $box[2] - $box[0] );
			$x    = (int) ( ( $w - $tw ) / 2 );
			$y    = (int) ( $h * 0.42 );
			imagettftext( $im, $size, 0, $x, $y, $tcolor, $font, 'Entity Link Engine' );
			if ( file_exists( $font2 ) ) {
				$s2   = max( 12, (int) ( $h * 0.07 ) );
				$box2 = imagettfbbox( $s2, 0, $font2, 'Entity mapping - Fan-out queries - Score-ranked links' );
				$tw2  = abs( $box2[2] - $box2[0] );
				imagettftext( $im, $s2, 0, (int) ( ( $w - $tw2 ) / 2 ), (int) ( $h * 0.60 ), $sub, $font2, 'Entity mapping - Fan-out queries - Score-ranked links' );
			}
		}
	}
	return $im;
}

// ---- Icon 256 / 128 -------------------------------------------------------
foreach ( array( 256, 128 ) as $size ) {
	$im = render( $size, $size, $bg, $bg2, $accent, $accent2, $line, $text, true );
	imagepng( $im, "$out/icon-{$size}x{$size}.png" );
	imagedestroy( $im );
	echo "icon-{$size}x{$size}.png\n";
}

// ---- Banners -----------------------------------------------------------------
$banners = array(
	array( 1544, 500, 'banner-1544x500.png' ),
	array( 772, 250, 'banner-772x250.png' ),
);
foreach ( $banners as $b ) {
	$im = render( $b[0], $b[1], $bg, $bg2, $accent, $accent2, $line, $text, false );
	imagepng( $im, "$out/{$b[2]}" );
	imagedestroy( $im );
	echo "{$b[2]}\n";
}

// ---- SVG icon (recommended by the directory) ---------------------------------
$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#0d1828"/>
      <stop offset="1" stop-color="#162a44"/>
    </linearGradient>
  </defs>
  <rect width="256" height="256" rx="48" fill="url(#g)"/>
  <g stroke="#78a0c8" stroke-width="7" stroke-linecap="round">
    <line x1="88" y1="112" x2="128" y2="168"/>
    <line x1="168" y1="112" x2="128" y2="168"/>
  </g>
  <circle cx="88" cy="100" r="26" fill="#48bbff"/>
  <circle cx="168" cy="100" r="26" fill="#48bbff"/>
  <circle cx="128" cy="182" r="22" fill="#adffd6"/>
</svg>
SVG;
file_put_contents( "$out/icon.svg", $svg );
echo "icon.svg\n";
