<nav>
    <ul>
        <% loop $Items %>
            <li>
                <% if $Link %>
                    <a href="$Link"<% if $OpenInNewWindow %> target="_blank"<% end_if %>>$Title</a>
                <% else %>
                    <span>$Title</span>
                <% end_if %>
            </li>
        <% end_loop %>
    </ul>
</nav>
