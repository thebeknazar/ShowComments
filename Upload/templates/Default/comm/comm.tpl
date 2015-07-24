[comm]
<li>
	<div class="lcomm-item">
		<b class="lcomm-user">[color]{user_name}[/color]</b>
		<span class="lcomm-date">{date=d.m.Y}</span>
		<br>
		<a href="{full_link}" title="{long_title}">{title} <span>({comm_num}) / {rating}</span></a>
		
		<div class="lcomm-hidden">
			<img class="lcomm-user-foto" src="{foto}" alt="{user_name}-фото">
			<div class="lcomm-text">
				{text}
			</div>
		</div>
	</div>
</li>
[/comm]
[not-comm]
<center>{error}</center>
[/not-comm]