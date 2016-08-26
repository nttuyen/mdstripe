{*
 * 2016 Michael Dekker
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@michaeldekker.com so we can send you a copy immediately.
 *
 *  @author    Michael Dekker <prestashop@michaeldekker.com>
 *  @copyright 2016 Michael Dekker
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
{if $smarty.const._PS_VERSION_|@addcslashes:'\'' < '1.6'}
	<fieldset>
		<legend>{l s='Check for updates' mod='mdstripe'}</legend>
		<p>
			<strong>{l s='Check if this module needs updates' mod='mdstripe'}</strong><br />
		</p>
		{if $needsUpdate}
			<div class="warn">
				{l s='This module needs to be updated to version %s' mod='mdstripe' sprintf=[$latestVersion]}
			</div>
		{else}
			<div class="confirm">
				{l s='This module is up to date.' mod='mdstripe'}
			</div>
		{/if}
		<br />
		<a class="button" href="{$baseUrl}&mdstripeCheckUpdate=1"><i class="icon icon-search"></i> {l s='Check for updates' mod='mdstripe'}</a>
		{if $needsUpdate}
			<a class="button" href="{$baseUrl}&mdstripeApplyUpdate=1"><i class="icon icon-refresh"></i> {l s='Update module' mod='mdstripe'}</a>
		{/if}
	</fieldset>
	<br />
{else}
	<div class="panel">
		<h3><i class="icon icon-refresh"></i> {l s='Check for updates' mod='mdstripe'}</h3>
		<p>
			<strong>{l s='Check if this module needs updates' mod='mdstripe'}</strong><br />
		</p>
		{if $needsUpdate}
			<div class="alert alert-warning">
				{l s='This module needs to be updated to version %s' mod='mdstripe' sprintf=[$latestVersion]}
			</div>
		{else}
			<div class="alert alert-success">
				{l s='This module is up to date.' mod='mdstripe'}
			</div>
		{/if}
		<a class="btn btn-default" href="{$baseUrl}&mdstripeCheckUpdate=1"><i class="icon icon-search"></i> {l s='Check for updates' mod='mdstripe'}</a>
		{if $needsUpdate}
			<a class="btn btn-default" href="{$baseUrl}&mdstripeApplyUpdate=1"><i class="icon icon-refresh"></i> {l s='Update module' mod='mdstripe'}</a>
		{/if}
	</div>
{/if}
