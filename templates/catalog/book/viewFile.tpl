{**
 * templates/catalog/book/viewFile.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a public-facing book view in the catalog.
 *}
{strip}
{assign var="pageTitleTranslated" value=$publishedMonograph->getLocalizedFullTitle()}
{include file="common/header.tpl"}
{/strip}

<div class="pkp_catalog_bookFile">

{$viewableFileContent}

</div><!-- pkp_catalog_book -->

{include file="common/footer.tpl"}
