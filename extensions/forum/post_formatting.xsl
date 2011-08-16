<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0" 
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php">
	
	<xsl:output method="xml" omit-xml-declaration="yes" />
  <xsl:namespace-alias stylesheet-prefix="php" result-prefix="xsl" />

  <xsl:template match="code | Code | CODE" priority="5">
    <xsl:variable name="content"><xsl:copy-of select="node()" /></xsl:variable>
    <div style="font-family: Courier, 'Courier New', monospace; padding-left: 10px; border-left: 1px solid #FF671C; color: #FFFFFF; background-color: #000000; padding: 5px;">
      <pre style="white-space: pre-wrap; word-wrap: break-word;"><xsl:value-of select="php:function( 'xsl::escape_xml', self::node() )" /></pre>
    </div>
  </xsl:template>
	
	<xsl:template match="quote | QUOTE | Quote" name="quote" priority="1">
		<div class="quote_left"><div class="quote_right">
			<div class="quote_content">
        <xsl:if test="string-length( @author ) > 0">
          <div class="quote_author">
						<xsl:value-of select="@author" />
            <xsl:choose>
              <xsl:when test="string-length( @source ) > 0">
                <xsl:text> said in </xsl:text>
                <xsl:choose>
                  <xsl:when test="string-length( @url ) > 0">
                    <a href="{@url}"><xsl:value-of select="@source" /></a>:
                  </xsl:when>
                  <xsl:otherwise>
                    <xsl:value-of select="@source" />:
                  </xsl:otherwise>
                </xsl:choose>
              </xsl:when>
              <xsl:otherwise> said:</xsl:otherwise>
            </xsl:choose>
          </div>
        </xsl:if>
        <xsl:apply-templates />
      </div>
		</div></div>
	</xsl:template>
	
	<xsl:template match="blockquote | BLOCKQUOTE | Blockquote" priority="1">
		<xsl:call-template name="quote" />
	</xsl:template>
	
	<xsl:template match="list" name="list" priority="1">
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

  <xsl:template match="font | FONT | Font" priority="1">
    <span>
      <xsl:attribute name="style">
        <xsl:if test="@size and number(@size) > 5 and number(@size) &lt; 24">font-size: <xsl:value-of select="@size" />px;</xsl:if>
        <xsl:if test="@color">color: <xsl:value-of select="@color" />;</xsl:if>
      </xsl:attribute>
      <xsl:apply-templates />
    </span>
  </xsl:template>
	
	<xsl:template match="list/* | LIST/* | List/*" priority="1">
		<xsl:apply-templates />
	</xsl:template>
	
	<xsl:template name="list_item" priority="1">
		<xsl:param name="index">1</xsl:param>
		<xsl:choose>
			<xsl:when test="parent::node()/@type = 'ordered'">
				<div class="olitem"><strong><xsl:for-each select="ancestor::list"><xsl:if test="count(ancestor::list)>0"><xsl:value-of select="count( preceding-sibling::item )" />.</xsl:if></xsl:for-each><xsl:value-of select="count( preceding-sibling::item )+1" />.</strong><span style="padding-left:10px;"><xsl:apply-templates /></span></div>
			</xsl:when>
			<xsl:otherwise>
				<div class="ulitem"><xsl:apply-templates /></div>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<xsl:template match="b | B | strong | Strong | STRONG" priority="1">
		<strong><xsl:apply-templates /></strong>
	</xsl:template>
	
	<xsl:template match="em | EM | Em | i | I" priority="1">
		<em><xsl:apply-templates /></em>
	</xsl:template>
	
	<xsl:template match="u | U" priority="1">
		<span style="text-decoration: underline;"><xsl:apply-templates /></span>
	</xsl:template>
	
	<xsl:template match="s | S" priority="1">
		<span style="text-decoration: line-through;"><xsl:apply-templates /></span>
	</xsl:template>
	
	<xsl:template match="a | A" priority="1">
		<a href="{@href}" target="{@target}" rel="nofollow" name="{@name}"><xsl:apply-templates /></a>
	</xsl:template>
	
	<xsl:template match="left | LEFT | Left" priority="1">
    <div style="float: left; padding-right: 10px;"><xsl:apply-templates /></div>
  </xsl:template>

  <xsl:template match="right | RIGHT | Right" priority="1">
    <div style="float: right padding-right: 10px;"><xsl:apply-templates /></div>
  </xsl:template>

  <xsl:template match="center | CENTER | Center" priority="1">
		<div style="text-align:center;">
			<xsl:apply-templates />
		</div>
	</xsl:template>
	
	<xsl:template match="caption | CAPTION | Caption" priority="1">
		<div style="border-bottom:1px solid #555555;background-color:#222222;padding-bottom: 5px;display: inline-block;">
			<div style="border:1px solid #000000;">
				<xsl:apply-templates />
			</div>
			<div style="padding-top: 5px;font-size:11px;" priority="1">
				<xsl:value-of select="@text" />
			</div>
		</div>
	</xsl:template>
	
	<xsl:template match="img | IMG | Img" priority="1">
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
		</img>
	</xsl:template>

  <xsl:template match="block | Block | BLOCK" priority="1">
    <div style="float: {@float}">
      <xsl:apply-templates />
    </div>
  </xsl:template>
	
	<xsl:template match="*">
		<xsl:apply-templates />
	</xsl:template>

</xsl:stylesheet>