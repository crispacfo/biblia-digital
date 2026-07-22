<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9">

	<xsl:output method="html" encoding="UTF-8" indent="yes" />

	<xsl:template match="/">
		<html lang="pt-BR">
			<head>
				<meta charset="UTF-8" />
				<title>Sitemap XML</title>
				<style type="text/css">
					body {
						margin: 0;
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
						font-size: 14px;
						color: #1f2937;
						background: #f7f8fb;
					}
					.bdwp70-header {
						background: #3f72ea;
						color: #fff;
						padding: 22px 30px 20px;
					}
					.bdwp70-header h1 {
						margin: 0 0 26px;
						font-size: 30px;
						font-weight: 400;
						line-height: 1.2;
					}
					.bdwp70-header p {
						margin: 0;
						font-size: 15px;
						line-height: 1.8;
					}
					.bdwp70-header a {
						color: #fff;
						text-decoration: underline;
						font-weight: 500;
					}
					.bdwp70-wrap {
						max-width: 1300px;
						margin: 34px auto;
						padding: 0 24px;
					}
					.bdwp70-summary {
						margin: 0 0 16px;
						color: #374151;
					}
					table {
						width: 100%;
						border-collapse: collapse;
						background: #fff;
						box-shadow: 0 1px 2px rgba(0,0,0,.04);
					}
					th {
						background: #3f72ea;
						color: #fff;
						text-align: left;
						padding: 14px 18px;
						font-weight: 700;
					}
					td {
						border-bottom: 1px solid #e5e7eb;
						padding: 13px 18px;
						vertical-align: top;
					}
					tr:nth-child(even) td {
						background: #fbfcff;
					}
					td a {
						color: #1d4ed8;
						word-break: break-all;
					}
					.bdwp70-muted {
						color: #6b7280;
					}
				</style>
			</head>
			<body>
				<div class="bdwp70-header">
					<h1>Sitemap XML</h1>
					<p>
						Este sitemap XML é gerado pelo plugin para WordPress <strong>Bíblia Digital</strong>.
						Saiba mais em <a href="https://estudobiblico.org">estudobiblico.org</a>.
					</p>
				</div>

				<div class="bdwp70-wrap">
					<xsl:choose>
						<xsl:when test="sm:sitemapindex">
							<p class="bdwp70-summary">
								Este arquivo de indexação de sitemap XML contém <strong><xsl:value-of select="count(sm:sitemapindex/sm:sitemap)" /></strong> sitemaps.
							</p>
							<table>
								<thead>
									<tr>
										<th>Sitemap</th>
										<th>Última modificação</th>
									</tr>
								</thead>
								<tbody>
									<xsl:for-each select="sm:sitemapindex/sm:sitemap">
										<tr>
											<td><a href="{sm:loc}"><xsl:value-of select="sm:loc" /></a></td>
											<td>
												<xsl:choose>
													<xsl:when test="sm:lastmod"><xsl:value-of select="sm:lastmod" /></xsl:when>
													<xsl:otherwise><span class="bdwp70-muted">—</span></xsl:otherwise>
												</xsl:choose>
											</td>
										</tr>
									</xsl:for-each>
								</tbody>
							</table>
						</xsl:when>

						<xsl:when test="sm:urlset">
							<p class="bdwp70-summary">
								Este arquivo de sitemap XML contém <strong><xsl:value-of select="count(sm:urlset/sm:url)" /></strong> URLs.
							</p>
							<table>
								<thead>
									<tr>
										<th>URL</th>
										<th>Última modificação</th>
									</tr>
								</thead>
								<tbody>
									<xsl:for-each select="sm:urlset/sm:url">
										<tr>
											<td><a href="{sm:loc}"><xsl:value-of select="sm:loc" /></a></td>
											<td>
												<xsl:choose>
													<xsl:when test="sm:lastmod"><xsl:value-of select="sm:lastmod" /></xsl:when>
													<xsl:otherwise><span class="bdwp70-muted">—</span></xsl:otherwise>
												</xsl:choose>
											</td>
										</tr>
									</xsl:for-each>
								</tbody>
							</table>
						</xsl:when>

						<xsl:otherwise>
							<p class="bdwp70-summary">Este XML não contém um sitemapindex ou urlset reconhecido.</p>
						</xsl:otherwise>
					</xsl:choose>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
