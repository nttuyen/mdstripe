<nav class="navbar navbar-default" role="navigation">
	<ul class="nav navbar-nav">
		{if isset($menutabs)}
			{foreach from=$menutabs item=tab}
				<li class="{if $tab.active}active{/if}">
					<a id="{$tab.short|escape:'htmlall':'UTF-8'}" href="{$tab.href|escape:'htmlall':'UTF-8'}">
						<span class="icon {$tab.icon|escape:'htmlall':'UTF-8'}"></span>
						{$tab.desc|escape:'htmlall':'UTF-8'}
					</a>
				</li>
			{/foreach}
		{/if}
	</ul>
</nav>