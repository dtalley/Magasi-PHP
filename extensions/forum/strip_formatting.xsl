<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0" 
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php">
	
	<xsl:output method="xml" omit-xml-declaration="yes" />
  <xsl:namespace-alias stylesheet-prefix="php" result-prefix="xsl" />
	
	<xsl:template match="*">
		<xsl:apply-templates />
	</xsl:template>

</xsl:stylesheet>