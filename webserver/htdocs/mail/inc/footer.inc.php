<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.9.4/js/bootstrap-select.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/6.0.16/bootstrap-slider.min.js"></script>
<script src='https://www.google.com/recaptcha/api.js'></script>

<script>
$(document).ready(function() {
	$('select').selectpicker();

	$('[data-action="filter"]').filterTable();
	$('.container').on('click', '.panel-heading span.filter', function(e){
		var $this = $(this),
		$panel = $this.parents('.panel');
		$panel.find('.panel-body').slideToggle("fast");
		if($this.css('display') != 'none') {
			$panel.find('.panel-body input').focus();
		}
	});
	$('[data-toggle="tooltip"]').tooltip();

	$("#alert-fade").fadeTo(2000, 500).slideUp(500, function(){
		$("#alert-fade").alert('close');
	});
	<?php
	if (isset($username)):
	?>
	$("#score").slider({ id: "slider1", min: 1, max: 30, step: 1, range: true, value: [<?=get_spam_score($link, $username);?>] });
	<?php
	endif;
	?>
});
</script>
</body>
</html>
<?php mysqli_close($link); ?>
