{+START,BOX,{TITLE},,{$?,{$GET,in_panel},panel,classic},,,{$?,{$IS_NON_EMPTY,{ARCHIVE_URL}},<a rel="archives" href="{ARCHIVE_URL*}">{!VIEW_ARCHIVE}</a>|}{$?,{$IS_NON_EMPTY,{SUBMIT_URL}},<a rel="add" href="{SUBMIT_URL*}">{!ADD_NEWS}</a>|}}
	{+START,IF_EMPTY,{CONTENT}}
		<p class="block_no_entries">&raquo; {!NO_NEWS}</p>
	{+END}
	{+START,IF_NON_EMPTY,{CONTENT}}
		<div class="xhtml_validator_off">
			{CONTENT}
		</div>
	{+END}
{+END}

