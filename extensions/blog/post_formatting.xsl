<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:magasi="http://www.magasi-php.com/magasi"
                xmlns:php="http://php.net/xsl"
                xsl:extension-element-prefixes="php magasi">
	
	<xsl:output method="xml"
              omit-xml-declaration="yes" />

  <xsl:template match="tm">&#8482;</xsl:template>
  <xsl:template match="copy">&#169;</xsl:template>
  <xsl:template match="reg">&#174;</xsl:template>

  <xsl:template match="bwquote | BWQUOTE | Bwquote | BWQuote" name="bwquote" priority="1">
    <div class="bwquote_left"><div class="bwquote_right">
      <div class="bwquote_content">
        <xsl:choose>
          <xsl:when test="@thread and @author and @source">
            <div class="bwquote_author">In "<a href="{@source}"><xsl:value-of select="@thread" /></a>", <xsl:value-of select="@author" /> said:</div>
          </xsl:when>
          <xsl:when test="@author and @source">
            <div class="bwquote_author"><a href="{@source}" rel="nofollow"><xsl:value-of select="@author" /></a> said:</div>
          </xsl:when>
          <xsl:when test="@author">
            <div class="bwquote_author"><xsl:value-of select="@author" /> said:</div>
          </xsl:when>
          <xsl:when test="@source">
            <div class="bwquote_author">(<a href="{@source}" rel="nofollow">Source</a>):</div>
          </xsl:when>
        </xsl:choose>
      <xsl:apply-templates />
      </div>
    </div></div>
  </xsl:template>

  <xsl:template match="quote | QUOTE | Quote" name="quote" priority="1">
    <div class="quote_left"><div class="quote_right">
      <div class="quote_content">
        <xsl:choose>
          <xsl:when test="@author and @source">
            <div class="quote_author"><a href="{@source}" rel="nofollow"><xsl:value-of select="@author" /></a> said:</div>
          </xsl:when>
          <xsl:when test="@author">
            <div class="quote_author"><xsl:value-of select="@author" /> said:</div>
          </xsl:when>
          <xsl:when test="@source">
            <div class="quote_author">(<a href="{@source}" rel="nofollow">Source</a>):</div>
          </xsl:when>
        </xsl:choose>
      <xsl:apply-templates />
      </div>
    </div></div>
  </xsl:template>

	<xsl:template match="blockquote">
		<xsl:call-template name="quote" />
	</xsl:template>

  <xsl:template match="ul" name="ul">
		<div class="news_list">
			<xsl:for-each select="child::node()">
				<xsl:choose>
					<xsl:when test="name() = 'li'">
						<xsl:call-template name="list_item" />
					</xsl:when>
					<xsl:when test="name() = 'ul'">
						<xsl:call-template name="ul" />
					</xsl:when>
				</xsl:choose>
			</xsl:for-each>
		</div>
	</xsl:template>

	<xsl:template match="list" name="list">
		<div class="news_list">
			<xsl:for-each select="child::node()">
				<xsl:choose>
					<xsl:when test="name() = 'item'">
						<xsl:call-template name="list_item" />
					</xsl:when>
					<xsl:when test="name() = 'list'">
						<xsl:call-template name="list" />
					</xsl:when>
				</xsl:choose>
			</xsl:for-each>
		</div>
	</xsl:template>

	<xsl:template match="list/*">
		<xsl:apply-templates />
	</xsl:template>

	<xsl:template name="list_item">
		<xsl:param name="index">1</xsl:param>
		<xsl:choose>
			<xsl:when test="parent::node()/@type = 'ordered'">
				<div class="olitem"><strong><xsl:value-of select="count( preceding-sibling::item )+1" />.</strong><span style="padding-left:10px;"><xsl:apply-templates /></span></div>
			</xsl:when>
			<xsl:otherwise>
				<div class="ulitem"><xsl:apply-templates /></div>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="b">
		<strong><xsl:apply-templates /></strong>
	</xsl:template>

  <xsl:template match="strong">
		<strong><xsl:apply-templates /></strong>
	</xsl:template>

	<xsl:template match="i">
		<em><xsl:apply-templates /></em>
	</xsl:template>

	<xsl:template match="em">
		<em><xsl:apply-templates /></em>
	</xsl:template>

	<xsl:template match="u">
		<span style="text-decoration: underline;"><xsl:apply-templates /></span>
	</xsl:template>

	<xsl:template match="s">
		<span style="text-decoration: strikeout;"><xsl:apply-templates /></span>
	</xsl:template>

	<xsl:template match="a">
		<a href="{@href}" rel="{@rel}"><xsl:apply-templates /></a>
	</xsl:template>

	<xsl:template match="center">
		<div style="text-align:center;">
			<xsl:apply-templates />
		</div>
	</xsl:template>

  <xsl:template match="right">
    <div style="text-align: right;">
      <xsl:apply-templates />
    </div>
  </xsl:template>

	<xsl:template match="caption">
    <xsl:param name="style_dir" select="php:function('xsl::get_style_dir')" />
		<div style="margin: 0 auto;display: inline-block;">
			<div style="line-height: 0px;font-size: 0px; background-color: #333333; padding: 3px;display: inline-block;">
				<xsl:apply-templates />
			</div>
      <xsl:if test="string-length( @text ) > 0">
        <div style="background: #1A1A1A url( '{$style_dir}/images/blog/featured_left.gif' ) bottom left no-repeat;">
          <div style="background: url( '{$style_dir}/images/blog/featured_right.gif' ) bottom right no-repeat; padding: 7px; color: #FFFFFF; font-size: 11px; font-style: italic;">
          <xsl:value-of select="@text" />
          </div>
        </div><br />
      </xsl:if>
		</div>
	</xsl:template>

	<xsl:template match="img">
		<img src="{@src}">
			<xsl:if test="string-length( @width ) > 0">
				<xsl:attribute name="width">
					<xsl:value-of select="@width" />
				</xsl:attribute>
			</xsl:if>
			<xsl:if test="string-length( @height ) > 0">
				<xsl:attribute name="height">
					<xsl:value-of select="@height" />
				</xsl:attribute>
			</xsl:if>
                        <xsl:if test="string-length( @alt ) > 0">
                          <xsl:attribute name="alt">
                            <xsl:value-of select="@alt" />
                          </xsl:attribute>
                        </xsl:if>
		</img>
	</xsl:template>

	<xsl:template match="block">
		<div>
      <xsl:attribute name="style">
        <xsl:if test="@float = 'left'">float: left; padding-right: 10px;</xsl:if>
        <xsl:if test="@float = 'right'">float: right; padding-left: 10px;</xsl:if>
      </xsl:attribute>
			<xsl:apply-templates />
		</div>
	</xsl:template>

  <xsl:template match="lightbox">
    <a href="{@src}" class="lightbox">
      <xsl:if test="string-length( @group ) > 0">
        <xsl:attribute name="rel">lightbox[<xsl:value-of select="@group" />]</xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @caption ) > 0">
        <xsl:attribute name="title"><xsl:value-of select="@caption" /></xsl:attribute>
      </xsl:if>
      <xsl:apply-templates />
    </a>
  </xsl:template>

  <xsl:template match="font">
    <span>
      <xsl:attribute name="style">
        <xsl:if test="string-length( @color ) > 0">color: <xsl:value-of select="@color" />;</xsl:if>
        <xsl:if test="string-length( @size ) > 0">font-size: <xsl:value-of select="@size" />px;</xsl:if>
      </xsl:attribute>
      <xsl:apply-templates />
    </span>
  </xsl:template>

  <xsl:template match="object">
    <object>
      <xsl:if test="string-length( @width ) > 0">
        <xsl:attribute name="width">
          <xsl:value-of select="@width" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @height ) > 0">
        <xsl:attribute name="height">
          <xsl:value-of select="@height" />
        </xsl:attribute>
      </xsl:if>
      <xsl:apply-templates />
    </object>
  </xsl:template>

  <xsl:template match="param">
    <param>
      <xsl:if test="string-length( @name ) > 0">
        <xsl:attribute name="name">
          <xsl:value-of select="@name" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @value ) > 0">
        <xsl:attribute name="value">
          <xsl:value-of select="@value" />
        </xsl:attribute>
      </xsl:if>
      <xsl:apply-templates />
    </param>
  </xsl:template>

  <xsl:template match="embed">
    <embed>
      <xsl:if test="string-length( @width ) > 0">
        <xsl:attribute name="width">
          <xsl:value-of select="@width" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @height ) > 0">
        <xsl:attribute name="height">
          <xsl:value-of select="@height" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @src ) > 0">
        <xsl:attribute name="src">
          <xsl:value-of select="@src" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @type ) > 0">
        <xsl:attribute name="type">
          <xsl:value-of select="@type" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @allowscriptaccess ) > 0">
        <xsl:attribute name="allowscriptaccess">
          <xsl:value-of select="@allowscriptaccess" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @allowfullscreen ) > 0">
        <xsl:attribute name="allowfullscreen">
          <xsl:value-of select="@allowfullscreen" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @allowFullScreen ) > 0">
        <xsl:attribute name="allowfullscreen">
          <xsl:value-of select="@allowFullScreen" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @salign ) > 0">
        <xsl:attribute name="salign">
          <xsl:value-of select="@salign" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @wmode ) > 0">
        <xsl:attribute name="wmode">
          <xsl:value-of select="@wmode" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @scale ) > 0">
        <xsl:attribute name="scale">
          <xsl:value-of select="@scale" />
        </xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length( @flashvars ) > 0">
        <xsl:attribute name="flashvars">
          <xsl:value-of select="@flashvars" />
        </xsl:attribute>
      </xsl:if>
      <xsl:apply-templates />
    </embed>
  </xsl:template>

  <xsl:template match="header">
    <xsl:param name="size"><xsl:if test="@size">font-size: <xsl:value-of select="@size" />px;</xsl:if></xsl:param>
    <xsl:param name="color"><xsl:if test="@color">color: <xsl:value-of select="@color" />;</xsl:if></xsl:param>
    <xsl:param name="border"><xsl:if test="@color">border-bottom: 1px solid <xsl:value-of select="@border" />; padding-bottom: 6px;</xsl:if></xsl:param>
    <xsl:param name="align"><xsl:if test="@align">text-align: <xsl:value-of select="@align" />;</xsl:if></xsl:param>
    <div style="font-weight: bold;{$size}{$color}{$border}{$align}"><xsl:apply-templates /></div>
  </xsl:template>

  <xsl:template match="podcast">
    <xsl:param name="root" select="php:function('xsl::get_root')" />
    <xsl:param name="style_dir" select="php:function('xsl::get_style_dir')" />
    <object class="flash_movie" id="podcast_{@id}" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="100%" height="44">
      <param name="movie" value="{$style_dir}/flash/podcast_player.swf?root_directory={$root}/&amp;podcast_id={@id}&amp;hide_menu=true" />
      <param name="wmode" value="transparent" />
      <!--[if !IE]>-->
      <object type="application/x-shockwave-flash" id="blog_image_manager_alt" data="{$style_dir}/flash/podcast_player.swf?root_directory={$root}/&amp;podcast_id={@id}&amp;hide_menu=true" width="100%" height="44">
      <!--<![endif]-->
        <param name="wmode" value="transparent" />
        <a href="/go/getflashplayer" rel="nofollow">
          <img src="http://beta.dhcdn.com/images/shared/download_buttons/get_flash_player.gif" width="112" height="33" alt="Get Adobe Flash player" />
        </a>
      <!--[if !IE]>-->
      </object>
      <!--<![endif]-->
    </object>
  </xsl:template>

  <xsl:template match="audio">
    <xsl:param name="root" select="php:function('xsl::get_root')" />
    <xsl:param name="style_dir" select="php:function('xsl::get_style_dir')" />
    <object class="flash_movie" id="audio_{@id}" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="100%" height="44">
      <param name="movie" value="{$style_dir}/flash/podcast_player.swf?&amp;podcast_id={@id}&amp;podcast_category={@type}&amp;hide_menu=true" />
      <param name="wmode" value="transparent" />
      <!--[if !IE]>-->
      <object type="application/x-shockwave-flash" data="{$style_dir}/flash/podcast_player.swf?&amp;podcast_id={@id}&amp;podcast_category={@type}&amp;hide_menu=true" width="100%" height="44">
      <!--<![endif]-->
        <param name="wmode" value="transparent" />
        <a href="/go/getflashplayer" rel="nofollow">
          <img src="http://beta.dhcdn.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" />
        </a>
      <!--[if !IE]>-->
      </object>
      <!--<![endif]-->
    </object>
  </xsl:template>

  <xsl:template match="poll">
    <div class="poll_container" id="poll_{@id}" />
  </xsl:template>

  <xsl:template match="table">
    <table width="{@width}" border="0" cellpadding="{@cellpadding}" cellspacing="{@cellspacing}">
      <xsl:apply-templates />
    </table>
  </xsl:template>

  <xsl:template match="tr">
    <tr>
      <xsl:apply-templates />
    </tr>
  </xsl:template>

  <xsl:template match="td">
    <td width="{@width}" height="{@height}" colspan="{@colspan}" rowspan="{@rowspan}" align="{@align}" valign="{@valign}">
      <xsl:attribute name="style">
        <xsl:if test="string-length( @style ) > 0">
          <xsl:value-of select="@style" />
        </xsl:if>
      </xsl:attribute>
      <div>
        <xsl:if test="string-length( @padding ) > 0">
          <xsl:attribute name="style">padding: <xsl:value-of select="@padding" />;</xsl:attribute>
        </xsl:if>
        <xsl:apply-templates />
      </div>
    </td>
  </xsl:template>

	<xsl:template match="*">
		<xsl:apply-templates />
	</xsl:template>

</xsl:stylesheet>