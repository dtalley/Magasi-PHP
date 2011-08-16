<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:output method="xml" omit-xml-declaration="yes" />
	
	<xsl:template match="img">
		<xsl:value-of select="@alt" />
	</xsl:template>
	
	<xsl:template match="*">
		<xsl:apply-templates />
	</xsl:template>

</xsl:stylesheet>