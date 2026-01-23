<?php

namespace seraph_accel;

if( !defined( 'ABSPATH' ) )
	exit;

function _Scripts_EncodeBodyAsSrc( $cont )
{

	$cont = str_replace( "%", '%25', $cont );

	$cont = str_replace( "\n", '%0A', $cont );
	$cont = str_replace( "#", '%23', $cont );
	$cont = str_replace( "\"", '%22', $cont );

	return( $cont );
}

function IsScriptTypeJs( $type )
{
	return( !$type || $type == 'application/javascript' || $type == 'text/javascript' || $type == 'module' );
}

function Script_SrcAddPreloading( $item, $src, $head, $doc )
{
	if( !$src )
		return;

	$itemPr = $doc -> createElement( 'link' );
	$itemPr -> setAttribute( 'rel', ( $item -> getAttribute( 'data-type' ) == 'module' || $item -> getAttribute( 'type' ) == 'module' ) ? 'modulepreload' : 'preload' );
	$itemPr -> setAttribute( 'as', $item -> tagName == 'IFRAME' ? 'document' : 'script' );
	$itemPr -> setAttribute( 'href', $src );
	$itemPr -> setAttribute( 'fetchpriority', 'low' );
	if( $item -> hasAttribute( 'integrity' ) )
		$itemPr -> setAttribute( "integrity", $item -> getAttribute( "integrity" ) );
	if( $item -> hasAttribute( "crossorigin" ) )
		$itemPr -> setAttribute( "crossorigin", $item -> getAttribute( "crossorigin" ) );
	$head -> appendChild( $itemPr );
}

function Scripts_Process( &$ctxProcess, $sett, $settCache, $settContPr, $settJs, $settCdn, $doc )
{
	if( ($ctxProcess[ 'isAMP' ]??null) )
	    return( true );

	$optLoad = Gen::GetArrField( $settJs, array( 'optLoad' ), false );
	$skips = Gen::GetArrField( $settJs, array( 'skips' ), array() );

	if( !( $optLoad || Gen::GetArrField( $settJs, array( 'groupNonCrit' ), false ) || Gen::GetArrField( $settJs, array( 'min' ), false ) || Gen::GetArrField( $settCdn, array( 'enable' ), false ) || $skips ) )
		return( true );

	$ctxOpt = new ScriptsOpt();
	if( isset( $ctxProcess[ 'lrnDsc' ] ) )
		$ctxOpt -> readLrnData( $ctxProcess, $ctxProcess[ 'lrnDsc' ], $ctxProcess[ 'lrnDataPath' ], isset( $ctxProcess[ 'lrn' ] ) );

	if( ($ctxProcess[ 'compatView' ]??null) )
		$optLoad = false;

	$head = $ctxProcess[ 'ndHead' ];
	$body = $ctxProcess[ 'ndBody' ];

	$aGrpExcl = Gen::GetArrField( $settJs, array( 'groupExcls' ), array() );
	$notCritsDelayTimeout = Gen::GetArrField( $settJs, array( 'nonCrit', 'timeout', 'enable' ), false ) ? Gen::GetArrField( $settJs, array( 'nonCrit', 'timeout', 'v' ), 0 ) : null;

	$critSpecsDelayTimeout = Gen::GetArrField( $settJs, array( 'critSpec', 'timeout', 'enable' ), false ) ? Gen::GetArrField( $settJs, array( 'critSpec', 'timeout', 'v' ), 0 ) : null;
	$critSpec = array();
	if( $critSpecsDelayTimeout !== null )
	{
		$critSpec = Gen::GetArrField( $settJs, array( 'critSpec', 'items' ), array() );
		if( isset( $ctxProcess[ 'aJsCritSpec' ] ) )
		{
			foreach( array_keys( $ctxProcess[ 'aJsCritSpec' ] ) as $expr )
				if( !in_array( $expr, $critSpec ) )
					$critSpec[] = $expr;
		}

		$critSpec = array_map( function( $v ) { return( $v . 'S' ); }, $critSpec );
	}

	$specsDelayTimeout = Gen::GetArrField( $settJs, array( 'spec', 'timeout', 'enable' ), false ) ? Gen::GetArrField( $settJs, array( 'spec', 'timeout', 'v' ), 0 ) : null;
	$specs = ( ( $notCritsDelayTimeout !== null && $specsDelayTimeout ) || ( $notCritsDelayTimeout === null && $specsDelayTimeout !== null ) ) ? Gen::GetArrField( $settJs, array( 'spec', 'items' ), array() ) : array();
	{
		$specs = array_map( function( $v ) { return( $v . 'S' ); }, $specs );
	}

	$settNonCrit = Gen::GetArrField( $settJs, array( 'nonCrit' ), array() );
	{
		$aItems = Gen::GetArrField( $settNonCrit, array( 'items' ), array() );

		if( isset( $ctxProcess[ 'aJsCrit' ] ) )
		{
			foreach( array_keys( $ctxProcess[ 'aJsCrit' ] ) as $expr )
				if( !in_array( $expr, $aItems ) )
					$aItems[] = $expr;
		}

		$aItems = array_map( function( $v ) { return( $v . 'S' ); }, $aItems );

		Gen::SetArrField( $settNonCrit, array( 'items' ), $aItems );
		unset( $aItems );
	}

	$delayNotCritNeeded = false;
	$delaySpecNeeded = false;

	$items = HtmlNd::ChildrenAsArr( $doc -> getElementsByTagName( 'script' ) );

	$contGroups = array( 'crit' => array( array( 0, 0 ), array( '' ) ), 'critSpec' => array( array( 0, 0 ), array( '' ) ), '' => array( array( 0, 0 ), array( '' ) ), 'spec' => array( array( 0, 0 ), array( '' ) ) );

	foreach( $items as $item )
	{
		if( ContentProcess_IsAborted( $settCache ) ) return( true );

		$type = HtmlNd::GetAttrVal( $item, 'type' );
		if( !IsScriptTypeJs( $type ) )
			continue;

		if( !ContentProcess_IsItemInFragments( $ctxProcess, $item ) )
			continue;

		if( apply_filters( 'seraph_accel_jscss_addtype', false ) )
		{
			if( !$type )
				$item -> setAttribute( 'type', $type = 'text/javascript' );
		}
		else if( $type && ($settContPr[ 'min' ]??null) && $type != 'module' )
		{
			$item -> removeAttribute( 'type' );
			$type = null;
		}

		$src = HtmlNd::GetAttrVal( $item, 'src' );
		$id = HtmlNd::GetAttrVal( $item, 'id' );
		$cont = $item -> nodeValue;

		{

		}

		$detectedPattern = null;
		if( IsObjInRegexpList( $skips, array( 'src' => $src, 'id' => $id, 'body' => $cont ), $detectedPattern ) )
		{
			if( ($ctxProcess[ 'debug' ]??null) )
			{
				$item -> setAttribute( 'type', 'o/js-inactive' );
				$item -> setAttribute( 'seraph-accel-debug', 'status=skipped;' . ( $detectedPattern ? ' detectedPattern="' . $detectedPattern . '"' : '' ) );
			}
			else
				$item -> parentNode -> removeChild( $item );
			continue;
		}

		$detectedPattern = null;
		if( $src )
		{
			$srcInfo = GetSrcAttrInfo( $ctxProcess, null, null, $src );

			$cont = null;
			$contMimeType = null;
			if( isset( $srcInfo[ 'filePath' ] ) && Gen::GetFileExt( $srcInfo[ 'filePath' ] ) == 'js' )
			{
				$cont = @file_get_contents( $srcInfo[ 'filePath' ] );
				if( $cont === false && !Gen::DoesFileDirExist( $srcInfo[ 'filePath' ], $srcInfo[ 'filePathRoot' ] ) )
					$cont = null;
			}
			else if( Ui::IsSrcAttrData( $src ) )
				$cont = Ui::GetSrcAttrData( $src, $contMimeType );

			if( $cont === null  )
			{

				$cont = GetExtContents( $ctxProcess, ($srcInfo[ 'url' ]??null), $contMimeType );
			}

			if( $contMimeType && $cont !== false && !in_array( $contMimeType, array( 'text/javascript', 'application/x-javascript', 'application/javascript' ) ) )
			{
				$cont = false;
				if( ($sett[ 'debug' ]??null) )
					LastWarnDscs_Add( LocId::Pack( 'JsUrlWrongType_%1$s%2$s', null, array( $srcInfo[ 'url' ], $contMimeType ) ) );
			}

			$isCrit = $item -> hasAttribute( 'seraph-accel-crit' ) ? true : GetObjSrcCritStatus( $settNonCrit, $critSpec, $specs, $srcInfo, $src, $id, $cont, $detectedPattern );

			$r = $ctxOpt -> AdjustCont( $ctxProcess, $settCache, $settJs, $srcInfo, $src, $id, $cont );
			if( $r === false )
				return( false );
			if( $r )
			{
				if( ($ctxProcess[ 'debug' ]??null) )
					$cont = '// ################################################################################################################################################' . "\r\n" . '// DEBUG: seraph-accel JS src="' . $src . '"' . "\r\n\r\n" . $cont;

				if( !UpdSc( $ctxProcess, $settCache, 'js', $cont, $src ) )
					return( false );
			}

			Cdn_AdjustUrl( $ctxProcess, $settCdn, $src, 'js' );
			Fullness_AdjustUrl( $ctxProcess, $src, ($srcInfo[ 'srcUrlFullness' ]??null) );

			$item -> setAttribute( 'src', $src );
		}
		else
		{
			if( !$cont )
				continue;

			$isCrit = $item -> hasAttribute( 'seraph-accel-crit' ) ? true : GetObjSrcCritStatus( $settNonCrit, $critSpec, $specs, null, null, $id, $cont, $detectedPattern );

			$r = $ctxOpt -> AdjustCont( $ctxProcess, $settCache, $settJs, null, null, $id, $cont );
			if( $r === false )
				return( false );
			if( $r )
			{
				if( ($ctxProcess[ 'debug' ]??null) )
					$cont = '// ################################################################################################################################################' . "\r\n" . '// DEBUG: seraph-accel JS src="inline:' . ($ctxProcess[ 'serverArgs' ][ 'REQUEST_SCHEME' ]??null) . '://' . $ctxProcess[ 'host' ] . ':' . ($ctxProcess[ 'serverArgs' ][ 'SERVER_PORT' ]??null) . ($ctxProcess[ 'serverArgs' ][ 'REQUEST_URI' ]??null) . ':' . $item -> getLineNo() . '"' . "\r\n\r\n" . $cont;

				HtmlNd::SetValFromContent( $item, $cont );
			}
		}

		ContUpdateItemIntegrity( $item, $cont );

		if( ($ctxProcess[ 'debug' ]??null) )
			$item -> setAttribute( 'seraph-accel-debug', 'status=' . ( $isCrit === true ? 'critical' : ( $isCrit === 'critSpec' ? 'criticalSpecial' : ( $isCrit === null ? 'special' : 'nonCritical' ) ) ) . ';' . ( $detectedPattern ? ' detectedPattern="' . $detectedPattern . '"' : '' ) );

		$delay = 0;
		if( $optLoad )
		{
			if( !$isCrit )
			{
				$parentNode = $item -> parentNode;
				$async = $item -> hasAttribute( 'async' );

				$delay = ( $isCrit === null ) ? $specsDelayTimeout : $notCritsDelayTimeout;

				if( $delay === 0 && ( !$async || ( $parentNode === $head || $parentNode === $body ) ) )
					$body -> appendChild( $item );
			}
			else if( $isCrit === 'critSpec' && !$item -> hasAttribute( 'async' ) )
			{
				$item -> setAttribute( 'defer', '' );
				if( !$src )
				{
					$src = 'data:text/javascript,' . _Scripts_EncodeBodyAsSrc( $cont );
					$item -> nodeValue = '';
					$item -> setAttribute( 'src', $src );
				}
			}

		}

		if( ($ctxProcess[ 'chunksEnabled' ]??null) )
			ContentMarkSeparate( $item, false );

		if( $delay )
		{
			if( $type )
				$item -> setAttribute( 'data-type', $type );

			if( $isCrit === null )
			{

				$item -> setAttribute( 'type', 'o/js-lzls' );
				$delaySpecNeeded = true;
			}
			else
			{

				$item -> setAttribute( 'type', 'o/js-lzl' );
				$delayNotCritNeeded = true;
			}
		}

		if( !($ctxProcess[ 'compatView' ]??null) && ($settJs[ $isCrit === true ? 'group' : ( $isCrit === 'critSpec' ? 'groupCritSpec' : ( $isCrit === null ? 'groupSpec' : 'groupNonCrit' ) ) ]??null) )
		{
			if( $ctxProcess[ 'mode' ] & 1 )
			{
				if( ($ctxProcess[ 'debug' ]??null) && is_string( $cont ) )
					$cont = '/* ################################################################################################################################################ */' . "\r\n" . '/* DEBUG: seraph-accel JS src="' . $src . '" */' . "\r\n\r\n" . $cont;

				$bGrpExcl = ( Gen::GetArrField( $settJs, array( 'groupExclMdls' ) ) && $type == 'module' ) || IsObjInRegexpList( $aGrpExcl, array( 'src' => $src, 'id' => $id, 'body' => $cont ) );

				if( $cont === false || $bGrpExcl )
					$cont = '';

				if( strlen( $cont ) )
				{

					if( substr( $cont, -1, 1 ) == ';' )
						$cont .= "\r\n";
					else
						$cont .= ";\r\n";

					if( ($ctxProcess[ 'chunksEnabled' ]??null) && Gen::GetArrField( $settCache, array( 'chunks', 'js' ) ) )
						$cont .= ContentMarkGetSep();

					if( $optLoad && $isCrit === false && $delayNotCritNeeded )
						$cont .= 'seraph_accel_gzjydy();';

				}

				$contGroup = &$contGroups[ $isCrit === true ? 'crit' : ( $isCrit === 'critSpec' ? 'critSpec' : ( $isCrit === null ? 'spec' : '' ) ) ];

				if( ( $item -> hasAttribute( 'defer' ) && $item -> getAttribute( 'defer' ) !== false ) && !( $item -> hasAttribute( 'async' ) && $item -> getAttribute( 'async' ) !== false ) && $src )
				{
					if( $bGrpExcl )
						array_splice( $contGroup[ 1 ], count( $contGroup[ 1 ] ), 0, array( $item, '' ) );

					$contGroup[ 1 ][ count( $contGroup[ 1 ] ) - 1 ] .= $cont;
				}
				else
				{
					if( $bGrpExcl )
					{
						array_splice( $contGroup[ 1 ], $contGroup[ 0 ][ 0 ], 1, array( substr( $contGroup[ 1 ][ $contGroup[ 0 ][ 0 ] ], 0, $contGroup[ 0 ][ 1 ] ), $item, substr( $contGroup[ 1 ][ $contGroup[ 0 ][ 0 ] ], $contGroup[ 0 ][ 1 ] ) ) );
						$contGroup[ 0 ][ 0 ] += 2;
						$contGroup[ 0 ][ 1 ] = 0;
					}

					$contGroup[ 1 ][ $contGroup[ 0 ][ 0 ] ] = substr_replace( $contGroup[ 1 ][ $contGroup[ 0 ][ 0 ] ], $cont, $contGroup[ 0 ][ 1 ], 0 );
					$contGroup[ 0 ][ 1 ] += strlen( $cont );
				}

				unset( $contGroup );
			}

			$item -> parentNode -> removeChild( $item );
		}
		else if( $delay && $isCrit === false && ($settJs[ 'preLoadEarly' ]??null) )
			Script_SrcAddPreloading( $item, $src, $head, $doc );
	}

	if( $optLoad )
	{
		foreach( HtmlNd::ChildrenAsArr( $doc -> getElementsByTagName( 'iframe' ) ) as $item )
		{
			if( ContentProcess_IsAborted( $settCache ) ) return( true );

			if( HtmlNd::FindUpByTag( $item, 'noscript' ) )
				continue;

			if( !Scripts_IsElemAs( $ctxProcess, $doc, $settJs, $item ) )
				continue;

			if( !ContentProcess_IsItemInFragments( $ctxProcess, $item ) )
				continue;

			$src = HtmlNd::GetAttrVal( $item, 'src' );
			$id = HtmlNd::GetAttrVal( $item, 'id' );
			$srcInfo = GetSrcAttrInfo( $ctxProcess, null, null, $src );

			$detectedPattern = null;
			$isCrit = GetObjSrcCritStatus( $settNonCrit, $critSpec, $specs, $srcInfo, $src, $id, null, $detectedPattern );

			Fullness_AdjustUrl( $ctxProcess, $src, ($srcInfo[ 'srcUrlFullness' ]??null) );
			$item -> setAttribute( 'src', $src );
			$item -> setAttribute( 'async', '' );

			if( ($ctxProcess[ 'debug' ]??null) )
				$item -> setAttribute( 'seraph-accel-debug', 'status=' . ( $isCrit === true ? 'critical' : ( $isCrit === 'critSpec' ? 'criticalSpecial' : ( $isCrit === null ? 'special' : 'nonCritical' ) ) ) . ';' . ( $detectedPattern ? ' detectedPattern="' . $detectedPattern . '"' : '' ) );

			if( $isCrit )
				continue;

			$delay = ( $isCrit === null ) ? $specsDelayTimeout : $notCritsDelayTimeout;
			if( !$delay )
				continue;

			HtmlNd::RenameAttr( $item, 'src', 'data-src' );
			if( $isCrit === null )
			{
				$item -> setAttribute( 'type', 'o/js-lzls' );
				$delaySpecNeeded = true;
			}
			else
			{
				$item -> setAttribute( 'type', 'o/js-lzl' );
				$delayNotCritNeeded = true;
			}
		}
	}

	if( !( $ctxProcess[ 'mode' ] & 1 ) )
		return( true );

	$itemGrpCritLast = null;
	foreach( $contGroups as $contGroupId => $contGroup )
	{
		foreach( $contGroup[ 1 ] as $cont )
		{
			if( !$cont )
				continue;

			if( is_string( $cont ) )
			{
				$item = $doc -> createElement( 'script' );
				if( apply_filters( 'seraph_accel_jscss_addtype', false ) )
					$item -> setAttribute( $item, 'type', 'text/javascript' );

				if( !GetContentProcessorForce( $sett ) && ($ctxProcess[ 'chunksEnabled' ]??null) && Gen::GetArrField( $settCache, array( 'chunks', 'js' ) ) )
				{
					$idSub = ( string )( $ctxProcess[ 'subCurIdx' ]++ ) . '.js';
					$ctxProcess[ 'subs' ][ $idSub ] = $cont;
					$src = ContentProcess_GetGetPartUri( $ctxProcess, $idSub );
				}
				else
				{
					$cont = str_replace( ContentMarkGetSep(), '', $cont );
					if( !UpdSc( $ctxProcess, $settCache, 'js', $cont, $src ) )
						return( false );
				}

				Cdn_AdjustUrl( $ctxProcess, $settCdn, $src, 'js' );
				Fullness_AdjustUrl( $ctxProcess, $src );
				$item -> setAttribute( 'src', $src );
			}
			else
				$item = $cont;

			if( $contGroupId === 'crit' || $contGroupId === 'critSpec' )
			{
				HtmlNd::InsertAfter( $head, $item, $itemGrpCritLast, true );
				$itemGrpCritLast = $item;

				if( $contGroupId === 'critSpec' )
					$item -> setAttribute( 'defer', '' );

				continue;
			}

			if( is_string( $cont ) && $optLoad )
			{
				$delay = ( $contGroupId === 'spec' ) ? $specsDelayTimeout : $notCritsDelayTimeout;
				if( $delay )
				{

					if( $contGroupId === 'spec' )
					{
						$item -> setAttribute( 'type', 'o/js-lzls' );
						$delaySpecNeeded = true;

						$delay = $specsDelayTimeout;
					}
					else
					{
						$item -> setAttribute( 'type', 'o/js-lzl' );
						$delayNotCritNeeded = true;

						$delay = $notCritsDelayTimeout;
					}

					if( $contGroupId === '' && ($settJs[ 'preLoadEarly' ]??null) )
						Script_SrcAddPreloading( $item, $src, $head, $doc );
				}
			}

			$body -> appendChild( $item );
		}
	}

	if( $delayNotCritNeeded || $delaySpecNeeded )
	{

		{

			$item = $doc -> createElement( 'script' );
			if( apply_filters( 'seraph_accel_jscss_addtype', false ) )
				$item -> setAttribute( 'type', 'text/javascript' );

			HtmlNd::SetValFromContent( $item, "function seraph_accel_cmn_calcSizes(a){a.style.setProperty(\"--seraph-accel-client-width\",\"\"+a.clientWidth+\"px\");a.style.setProperty(\"--seraph-accel-client-width-px\",\"\"+a.clientWidth);a.style.setProperty(\"--seraph-accel-client-height\",\"\"+a.clientHeight+\"px\");a.style.setProperty(\"--seraph-accel-dvh\",\"\"+window.innerHeight+\"px\")}(function(a){a.addEventListener(\"seraph_accel_calcSizes\",function(b){seraph_accel_cmn_calcSizes(a.documentElement)},{capture:!0,passive:!0});seraph_accel_cmn_calcSizes(a.documentElement)})(document)" );
			$body -> insertBefore( $item, $body -> firstChild );
		}

		$ctxProcess[ 'jsDelay' ] = array( 'a' => array( '_E_A1_', '_E_A2_', '_E_TM1_', '_E_TM2_', '_E_CJSD_', '_E_AD_', '_E_FSCRLD_', '_E_FCD_', '_E_FCDECS_', '_E_PRL_', '_E_LF_' ), 'v' => array( '"o/js-lzl"', '"o/js-lzls"', $notCritsDelayTimeout ? $notCritsDelayTimeout : 0, $specsDelayTimeout ? $specsDelayTimeout : 0, ($settJs[ 'cplxDelay' ]??null) ? 1 : 0, Gen::GetArrField( $settJs, array( 'aniDelay' ), 250 ), $notCritsDelayTimeout ? Gen::GetArrField( $settJs, array( 'scrlDelay' ), 0 ) : 0, Gen::GetArrField( $settJs, array( 'clk', 'delay' ), 250 ), json_encode( ( array )($ctxProcess[ 'aJsDelay_firstClickDelayExclCssSel' ]??null) ), ($settJs[ 'preLoadEarly' ]??null) ? 0 : 1, ($settJs[ 'loadFast' ]??null) ? 1 : 0 ) );

		unset( $firstClickDelayExclCssSel );
	}

	if( isset( $ctxProcess[ 'lrnDsc' ] ) )
	{
		if( isset( $ctxProcess[ 'lrn' ] ) )
		{
			if( $ctxProcess[ 'mode' ] & 4 )
				if( !$ctxOpt -> writeLrnData( $ctxProcess, $ctxProcess[ 'lrnDsc' ], $ctxProcess[ 'lrnDataPath' ] ) )
					return( false );
		}
		else
			$ctxOpt -> readLrnDataFinish( $ctxProcess, $ctxProcess[ 'lrnDsc' ], $ctxProcess[ 'lrnDataPath' ] );
	}

	return( true );
}

function Scripts_ProcessAddRtn( &$ctxProcess, $sett, $settCache, $settContPr, $settJs, $settCdn, $doc, $prms )
{

	$cont = str_replace( $prms[ 'a' ], $prms[ 'v' ], "(function(p,k,q,O,I,r,G,T,U,P,V,W,X,Y){function Q(){p.seraph_accel_js_lzl_initScrCustom&&p.seraph_accel_js_lzl_initScrCustom();if(v){var a=p[function(e){var b=\"\";e.forEach(function(c){b+=String.fromCharCode(c+3)});return b}([103,78,114,98,111,118])];!v.dkhjihyvjed&&a?v=void 0:(v.dkhjihyvjed=!0,v.jydy(a))}}function F(a,e=0,b){function c(){if(!a)return[];for(var d=[].slice.call(k.querySelectorAll('[type=\"'+a+'\"]')),g=0,f=d.length;g<f;g++){var h=d[g];if(h.hasAttribute(\"defer\")&&!1!==h.defer&&(!h.hasAttribute(\"async\")||\n!1===h.async)&&h.hasAttribute(\"src\")||\"module\"==h.getAttribute(\"data-type\"))d.splice(g,1),d.push(h),g--,f--}return d}function l(d=!1){Q();Y||d?w():q(w,e)}function n(d){d=d.ownerDocument;var g=d.seraph_accel_njsujyhmaeex={hujvqjdes:\"\",wyheujyhm:d[function(f){var h=\"\";f.forEach(function(m){h+=String.fromCharCode(m+3)});return h}([116,111,102,113,98])],wyhedbujyhm:d[function(f){var h=\"\";f.forEach(function(m){h+=String.fromCharCode(m+3)});return h}([116,111,102,113,98,105,107])],ujyhm:function(f){this.seraph_accel_njsujyhmaeex.hujvqjdes+=\nf},dbujyhm:function(f){this.write(f+\"\\n\")}};d[function(f){var h=\"\";f.forEach(function(m){h+=String.fromCharCode(m+3)});return h}([116,111,102,113,98])]=g.ujyhm;d[function(f){var h=\"\";f.forEach(function(m){h+=String.fromCharCode(m+3)});return h}([116,111,102,113,98,105,107])]=g.dbujyhm}function t(d){var g=d.ownerDocument,f=g.seraph_accel_njsujyhmaeex;if(f){if(f.hujvqjdes){var h=g.createElement(\"span\");d.parentNode.insertBefore(h,d.nextSibling);h.outerHTML=f.hujvqjdes}g[function(m){var x=\"\";m.forEach(function(J){x+=\nString.fromCharCode(J+3)});return x}([116,111,102,113,98])]=f.wyheujyhm;g[function(m){var x=\"\";m.forEach(function(J){x+=String.fromCharCode(J+3)});return x}([116,111,102,113,98,105,107])]=f.wyhedbujyhm;delete g.seraph_accel_njsujyhmaeex}}function w(){var d=u.shift();if(d)if(d.parentNode){var g=k.seraph_accel_usbpb(d.tagName),f=d.attributes;if(f)for(var h=0;h<f.length;h++){var m=f[h],x=m.value;m=m.name;\"type\"!=m&&(\"data-type\"==m&&(m=\"type\"),\"data-src\"==m&&(m=\"src\"),g.setAttribute(m,x))}g.textContent=\nd.textContent;f=!g.hasAttribute(\"async\");h=g.hasAttribute(\"src\");m=g.hasAttribute(\"nomodule\");f&&n(g);if(h=f&&h&&!m)g.onload=g.onerror=function(){g._seraph_accel_loaded||(g._seraph_accel_loaded=!0,t(g),l())};d.parentNode.replaceChild(g,d);h||(f&&t(g),l(!f))}else u=c(),w();else b&&b()}var u=c();if(X){var A=k.createDocumentFragment();u.forEach(function(d){var g=d?d.getAttribute(\"src\"):void 0;if(g){var f=k.createElement(\"link\");f.setAttribute(\"rel\",\"module\"==d.getAttribute(\"data-type\")?\"modulepreload\":\n\"preload\");f.setAttribute(\"as\",\"IFRAME\"==d.tagName?\"document\":\"script\");f.setAttribute(\"href\",g);d.hasAttribute(\"integrity\")&&f.setAttribute(\"integrity\",d.getAttribute(\"integrity\"));d.hasAttribute(\"crossorigin\")&&f.setAttribute(\"crossorigin\",d.getAttribute(\"crossorigin\"));A.appendChild(f)}});k.head.appendChild(A)}l()}function y(a,e,b){var c=k.createEvent(\"Events\");c.initEvent(e,!0,!1);if(b)for(var l in b)c[l]=b[l];a.dispatchEvent(c)}function H(a,e){function b(l){try{Object.defineProperty(k,\"readyState\",\n{configurable:!0,enumerable:!0,value:l})}catch(n){}}function c(l){r?(v&&(v.jydyut(),v=void 0),b(\"interactive\"),y(k,\"readystatechange\"),y(k,\"DOMContentLoaded\"),delete k.readyState,y(k,\"readystatechange\"),q(function(){y(p,\"load\");y(p,\"scroll\");e&&e();l()})):l()}if(z){if(3==z){function l(){r&&b(\"loading\");!0===a?F(r?O:0,10,function(){c(function(){2==z?(z=1,1E6!=G&&q(function(){H(!0)},G)):F(I)})}):F(r?O:0,0,function(){c(function(){F(I)})})}function n(){for(var t,w;void 0!==(t=Object.keys(seraph_accel_izrbpb.a)[0]);){for(;w=\nseraph_accel_izrbpb.a[t].shift();)if(w(n))return;delete seraph_accel_izrbpb.a[t]}\"scrl\"===a&&P?q(l,P):l()}n()}else 1==z&&F(I);!0===a?z--:z=0}}function K(a){return\"click\"==a||\"mouseover\"==a||\"touchstart\"==a||\"touchmove\"==a||\"touchend\"==a||\"pointerdown\"==a||\"pointermove\"==a||\"pointerup\"==a}function L(a){var e=!1;\"touchstart\"==a.type?B=!1:\"pointerdown\"==a.type?C=!1:!1===B&&\"touchmove\"==a.type?B=!0:!1===C&&\"pointermove\"==a.type&&(C=!0);if(K(a.type)){if(void 0!==D){e=!0;var b=!1,c=!1,l=!0;\"click\"==a.type?\nb=c=!0:\"mouseover\"==a.type?(b=!0,l=!1):\"touchmove\"==a.type?(e=!1,B&&(c=!0)):\"touchend\"==a.type?B&&(c=!0):\"pointerdown\"==a.type?c=!0:\"pointermove\"==a.type?(e=!1,C&&(c=!0)):\"pointerup\"==a.type&&C&&(c=!0);if(l){function t(u,A,d){return(u=n.getAttribute(d))&&(\"*\"===u||-1!=u.indexOf(\",\"+A+\",\"))}function w(u,A,d){if(!d)return!1;for(var g in d)if((\"*\"===g||-1!=g.indexOf(\",\"+A+\",\"))&&u.matches(d[g]))return!0;return!1}for(var n=a.target;n;n=n.parentNode)if(n.getAttribute){if(t(n,a.type,\"data-lzl-clk-no\")||\nw(n,a.type,W))e=!1;if(t(n,a.type,\"data-lzl-clk-nodef\")){e=!0;c&&(a.preventDefault(),a.stopImmediatePropagation());break}}}if(e){c=!1;if(b)for(b=0;b<D.length;b++)if(D[b].type==a.type){c=!0;break}c||D.push(a)}}}else k.removeEventListener(a.type,L,{passive:!0});\"touchend\"==a.type?B=void 0:\"pointerup\"==a.type&&(C=void 0);void 0===E?E=!0:!1===E&&\"touchstart\"!=a.type&&\"pointerdown\"!=a.type&&H(e||\"scroll\"!=a.type&&\"wheel\"!=a.type&&\"touchmove\"!=a.type&&\"pointermove\"!=a.type?!1:\"scrl\",M)}function M(){q(function(){R.forEach(function(a){k.removeEventListener(a,\nL,K(a)?{capture:!0,passive:!1}:{passive:!0})});k.body.classList.remove(\"seraph-accel-js-lzl-ing\");y(k,\"seraph_accel_jsFinish\");D.forEach(function(a){function e(c){return c&&!c.getAttribute(\"data-lzl-clk-no\")}if(\"click\"==a.type||\"mouseover\"==a.type){var b=k.elementFromPoint(a.clientX,a.clientY);e(b)&&b.dispatchEvent(new MouseEvent(a.type,{view:a.view,bubbles:!0,cancelable:!0,clientX:a.clientX,clientY:a.clientY}))}else if(\"touchstart\"==a.type||\"touchmove\"==a.type||\"touchend\"==a.type)b=(b=a.changedTouches&&\na.changedTouches.length?a.changedTouches[0]:void 0)?k.elementFromPoint(b.clientX,b.clientY):void 0,e(b)&&b.dispatchEvent(a);else if(\"pointerdown\"==a.type||\"pointermove\"==a.type||\"pointerup\"==a.type)b=k.elementFromPoint(a.clientX,a.clientY),e(b)&&b.dispatchEvent(a)});D=void 0},V);q(function(){k.body.classList.remove(\"seraph-accel-js-lzl-ing-ani\")},U)}function S(a){a.currentTarget&&a.currentTarget.removeEventListener(a.type,S);!0===E?(E=!1,H(!1,M)):(E=!1,1E6!=r&&q(function(){H(!0,M)},r))}function N(){q(function(){y(k,\n\"seraph_accel_calcSizes\")},0)}p.location.hash.length&&(r&&(r=1),G&&(G=1));r&&q(function(){k.body.classList.add(\"seraph-accel-js-lzl-ing-ani\")});var R=\"scroll wheel mousemove pointermove keydown click touchstart touchmove touchend pointerdown pointerup\".split(\" \"),E,B,C,v=T?{a:[],jydy:function(a){if(a&&a.fn&&!a.seraph_accel_bpb){this.a.push(a);a.seraph_accel_bpb={otquhdv:a.fn[function(e){var b=\"\";e.forEach(function(c){b+=String.fromCharCode(c+3)});return b}([111,98,94,97,118])]};if(a[function(e){var b=\n\"\";e.forEach(function(c){b+=String.fromCharCode(c+3)});return b}([101,108,105,97,79,98,94,97,118])])a[function(e){var b=\"\";e.forEach(function(c){b+=String.fromCharCode(c+3)});return b}([101,108,105,97,79,98,94,97,118])](!0);a.fn[function(e){var b=\"\";e.forEach(function(c){b+=String.fromCharCode(c+3)});return b}([111,98,94,97,118])]=function(e){k.addEventListener(\"DOMContentLoaded\",function(b){e.bind(k)(a,b)});return this}}},jydyut:function(){for(var a=0;a<this.a.length;a++){var e=this.a[a];e.fn[function(b){var c=\n\"\";b.forEach(function(l){c+=String.fromCharCode(l+3)});return c}([111,98,94,97,118])]=e.seraph_accel_bpb.otquhdv;delete e.seraph_accel_bpb;if(e[function(b){var c=\"\";b.forEach(function(l){c+=String.fromCharCode(l+3)});return c}([101,108,105,97,79,98,94,97,118])])e[function(b){var c=\"\";b.forEach(function(l){c+=String.fromCharCode(l+3)});return c}([101,108,105,97,79,98,94,97,118])](!1)}}}:void 0;p.seraph_accel_gzjydy=Q;var z=3,D=[];R.forEach(function(a){k.addEventListener(a,L,K(a)?{capture:!0,passive:!1}:\n{passive:!0})});p.addEventListener(\"load\",S);p.addEventListener(\"resize\",N,!1);k.addEventListener(\"DOMContentLoaded\",N,!1);p.addEventListener(\"load\",N)})(window,document,setTimeout,_E_A1_,_E_A2_,_E_TM1_,_E_TM2_,_E_CJSD_,_E_AD_,_E_FSCRLD_,_E_FCD_,_E_FCDECS_,_E_PRL_,_E_LF_)" );

	$item = $doc -> createElement( 'script' );
	if( apply_filters( 'seraph_accel_jscss_addtype', false ) )
		$item -> setAttribute( 'type', 'text/javascript' );

	$item -> setAttribute( 'id', 'seraph-accel-js-lzl' );

	HtmlNd::SetValFromContent( $item, $cont );

	$ctxProcess[ 'ndBody' ] -> appendChild( $item );

	ContentMarkSeparate( $item );

}

function Scripts_IsElemAs( &$ctxProcess, $doc, $settJs, $item )
{
	$items = &$ctxProcess[ 'scriptsInclItems' ];
	if( $items === null )
	{
		$items = array();

		$incls = Gen::GetArrField( $settJs, array( 'other', 'incl' ), array() );
		if( $incls )
		{
			$xpath = new \DOMXPath( $doc );

			foreach( $incls as $inclItemPath )
				foreach( HtmlNd::ChildrenAsArr( $xpath -> query( $inclItemPath, $ctxProcess[ 'ndHtml' ] ) ) as $itemIncl )
					$items[] = $itemIncl;
		}
	}

	return( in_array( $item, $items, true ) );
}

class ScriptsOpt
{
	protected $_aAdjustContCache;

	function __construct()
	{
		$this -> _aAdjustContCache = array();
	}

	static function keepLrnNeededData( &$datasDel, &$lrnsGlobDel, $dsc, $dataPath )
	{
		if( $id = Gen::GetArrField( $dsc, array( 'js', 'c' ) ) )
		{
			unset( $lrnsGlobDel[ 'js/c/' . $id . '.dat.gz' ] );

			if( ($dsc[ 'v' ]??null) < 2 )
			{
				$data = Tof_GetFileData( $dataPath . '/js/c', 'dat.gz', array( 1, function( $data, $vFrom ) { return( $data ); } ), true, $id );

				foreach( Gen::GetArrField( $data, array( 'ac' ), array() ) as $contPart )
					if( is_string( $contPart ) && strlen( $contPart ) )
						unset( $datasDel[ 'js' ][ $contPart ] );
			}
		}
	}

	function readLrnData( &$ctxProcess, $dsc, $dataPath, $bLearning )
	{
		if( $id = Gen::GetArrField( $dsc, array( 'js', 'c' ) ) )
		{
			$data = Tof_GetFileData( $dataPath . '/js/c', 'dat.gz', array( 1, function( $data, $vFrom ) { return( $data ); } ), true, $id );
			$this -> _aAdjustContCache = Gen::GetArrField( $data, array( 'ac' ), array() );
		}
	}

	function readLrnDataFinish( &$ctxProcess, $dsc, $dataPath )
	{
	}

	function writeLrnData( &$ctxProcess, &$dsc, $dataPath )
	{
		if( $this -> _aAdjustContCache )
		{
			$data = array();
			$data[ 'ac' ] = $this -> _aAdjustContCache;

			$dsc[ 'js' ][ 'c' ] = '';
			if( Gen::HrFail( @Tof_SetFileData( $dataPath . '/js/c', 'dat.gz', $data, 1, false, TOF_COMPR_MAX, $dsc[ 'js' ][ 'c' ] ) ) )
				return( false );
		}

		return( true );
	}

	public function AdjustCont( &$ctxProcess, $settCache, $settJs, $srcInfo, $src, $id, &$cont )
	{
		if( !$cont )
			return( null );

		if( isset( $ctxProcess[ 'lrnDsc' ] ) )
		{
			$contHash = md5( $cont, true );

			$res = ($this -> _aAdjustContCache[ $contHash ]??null);
			if( $res === false )
			{
				return( null );
			}
			else if( $res === '' )
			{
				$cont = '';
				return( true );
			}
			else if( is_string( $res ) && strlen( $res ) )
			{
				$contPart = ReadSc( $ctxProcess, $settCache, $res, 'js' );
				if( $contPart !== null )
				{
					$cont = $contPart;
					DepsAdd( $ctxProcess[ 'deps' ], 'js', $res );
					return( true );
				}
			}
		}

		$res = ScriptsOpt::_AdjustCont( $ctxProcess, $settCache, $settJs, $srcInfo, $src, $id, $cont );

		if( isset( $ctxProcess[ 'lrnDsc' ] ) && isset( $ctxProcess[ 'lrn' ] ) )
		{
			if( !$res )
				$this -> _aAdjustContCache[ $contHash ] = false;
			else
			{
				$oiCi = ( $cont !== '' ) ? UpdSc( $ctxProcess, $settCache, 'js', $cont ) : '';
				if( $oiCi === false )
					return( false );

				$this -> _aAdjustContCache[ $contHash ] = $oiCi;
			}
		}

		return( $res ? true : null );
	}

	static function _AdjustCont( &$ctxProcess, $settCache, $settJs, $srcInfo, $src, $id, &$cont )
	{
		$adjusted = false;
		if( ( !$srcInfo || !($srcInfo[ 'ext' ]??null) ) && Gen::GetArrField( $settJs, array( 'min' ), false ) && !IsObjInRegexpList( Gen::GetArrField( $settJs, array( 'minExcls' ), array() ), array( 'src' => $src, 'id' => $id, 'body' => $cont ) ) )
		{
			if( !( $ctxProcess[ 'mode' ] & 4 ) )
			{

				$ctxProcess[ 'modeReq' ] |= 4;
				return( false );
			}

			$contNew = trim( ScriptsOpt::JsMinify( $cont, ($settJs[ 'minMthd' ]??null), ($settJs[ 'cprRem' ]??null) ) );
			if( $cont != $contNew )
			{
				$cont = $contNew;
				$adjusted = true;
			}
		}

		return( $adjusted );
	}

	static function JsMinify( $cont, $method, $removeFlaggedComments = false )
	{
		try
		{
			switch( $method )
			{
				case 'jshrink':		$contNew = JShrink\Minifier::minify( $cont, array( 'flaggedComments' => !$removeFlaggedComments ) ); break;
				default:			$contNew = JSMin\JSMin::minify( $cont, array( 'removeFlaggedComments' => $removeFlaggedComments ) ); break;
			}
		}
		catch( \Exception $e )
		{
			return( $cont );
		}

		if( !$contNew )
			return( $cont );

		$cont = $contNew;

		if( ($ctxProcess[ 'debug' ]??null) )
			$cont = '/* DEBUG: MINIFIED by seraph-accel */' . $cont;

		return( $cont );
	}
}

