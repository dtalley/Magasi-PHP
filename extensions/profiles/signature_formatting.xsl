<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:output method="xml" omit-xml-declaration="yes" />
	
	<xsl:template match="b">
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
		<a rel="nofollow" href="{@href}">
      <xsl:if test="string-length(@target)>0">
        <xsl:attribute name="target"><xsl:value-of select="@target" /></xsl:attribute>
      </xsl:if>
      <xsl:apply-templates />
    </a>
	</xsl:template>

  <xsl:template match="left">
    <div style="float: left; padding-right: 10px;"><xsl:apply-templates /></div>
  </xsl:template>

  <xsl:template match="right">
    <div style="float: right padding-right: 10px;"><xsl:apply-templates /></div>
  </xsl:template>

  <xsl:template match="center">
		<div style="text-align:center;">
			<xsl:apply-templates />
		</div>
	</xsl:template>

  <xsl:template match="font">
    <span>
      <xsl:attribute name="style">
        <xsl:if test="@size and number(@size) > 5 and number(@size) &lt; 24">font-size: <xsl:value-of select="@size" />px;</xsl:if>
        <xsl:if test="@color">color: <xsl:value-of select="@color" />;</xsl:if>
      </xsl:attribute>
      <xsl:apply-templates />
    </span>
  </xsl:template>
	
	<xsl:template match="img">
		<!--<img src="{@src}" alt="{@alt}">
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
		</img>-->
    <xsl:apply-templates />
	</xsl:template>

  <xsl:template match="image">
    <!--<img src="{self::node()}" />-->
    <xsl:apply-templates />
  </xsl:template>
	
	<xsl:template match="*">
		<xsl:apply-templates />
	</xsl:template>

</xsl:stylesheet>