<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:output method="xml" omit-xml-declaration="yes" />
	
	<xsl:template match="b">
		<strong><xsl:apply-templates /></strong>
	</xsl:template>
	
	<xsl:template match="i">
		<em><xsl:apply-templates /></em>
	</xsl:template>
	
	<xsl:template match="u">
		<span style="text-decoration: underline;"><xsl:apply-templates /></span>
	</xsl:template>

  <xsl:template match="a">
    <a rel="nofollow">
      <xsl:if test="string-length( @href ) > 0">
        <xsl:attribute name="href">
          <xsl:value-of select="@href" />
        </xsl:attribute>
      </xsl:if>
      <xsl:apply-templates />
    </a>
  </xsl:template>

	<xsl:template match="*">
		<xsl:apply-templates />
	</xsl:template>

</xsl:stylesheet>