<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:output method="html" />
	
	<xsl:variable name="root" select="response/request/root" />
	
	<xsl:template match="response">
    <html>
      <head>
        <title></title>
        <style type="text/css">
          
        </style>
      </head>
      <body>
        
      </body>
    </html>
	</xsl:template>

</xsl:stylesheet>