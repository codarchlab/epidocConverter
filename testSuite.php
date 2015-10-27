<?php


	
	$file = (isset($_GET['file'])) ? $_GET['file'] : '';
	$forceFallback = (isset($_GET['mode']) and $_GET['mode']);
	
	
	if ($file) {
	
		$xml = file_get_contents($file);

		require_once('epidocConverter.class.php');
	   
		$error = false;
		$mode = "XML error";
	   
		try {
			
	   		$converter = epidocConverter::create($xml, $forceFallback);
	   		
	   		$mode = "Processor: " . (get_class($converter));
			
	   		$res = $converter->convert();
			
			$styles = $converter->getStylesheet();
			
		} catch (Exception $e) {
			$error = $e->getMessage();
	
		}
	
	
	} else {
		$mode = 'Enter an URL or path to a Epidoc File';
		$res = '';
		$xml = '';
		$error = false;
	}
?>
<html>
	<head>
	<title><?php echo $file  .  ' | ' . $mode; ?></title>
	<meta charset="utf-8">
	<style><?php echo $styles ?></style>
	<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.9.1/styles/default.min.css">
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
  	<script src="//code.jquery.com/jquery-1.10.2.js"></script>
  	<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/8.9.1/highlight.min.js"></script>
	<script>
		$(document).ready(function() {
		  $('code').each(function(i, block) {
		    hljs.highlightBlock(block);
		  });
		  $( "#tabs" ).tabs();
		});
	</script>

</head>


<body>
<div style="height:2%; width: 100%; margin-bottom: 1em">
	<form action="testSuite.php">
		<input type="text" style="border: 1px solid silver; width: 49%" value="<?php echo $file ?>" name="file" />
		<input type="checkbox" name="mode" id="chk_mode" <?php echo $forceFallback ? 'checked' : '' ?>><label for="chk_mode">Force Fallback Mode</label>
		<input type="submit" style="border: 1px solid silver; " />
		<?php echo $mode; ?>
	</form>
	
</div>

<?php if ($error) {?>
	<div style='height:97%; width: 100%; overflow: scroll; border: 1px solid red; padding: 2px; margin:0px"'>
		<?php echo $error; ?>
	</div>
<?php } else { ?>
	
	<code class="xml" style="height:96%; width: 49%; float: left; overflow: auto; border: 1px solid black; padding: 2px; margin:0px; white-space: pre"><?php echo htmlspecialchars($xml); ?></code>
	
	<div style="height:96%; width: 49%; float: right; overflow: auto; border: 1px solid black; padding: 2px" id='tabs'>
		<ul>
	    	<li><a href="#tabs-1">Result</a></li>
    		<li><a href="#tabs-2">Result Source Code</a></li>
		</ul>
		
		<div id="tabs-1">
			<?php echo $res; ?>
		</div>
		<div id="tabs-2"'>
			<code><?php echo htmlspecialchars($res); ?></code>
		</div>	
	</div>
<?php } ?>

</body>
</html>