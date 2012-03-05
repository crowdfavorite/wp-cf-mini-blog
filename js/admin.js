jQuery(function($) {
	// Edit row click
	$('a.edit_mb').click(function(e) {
		var rowID = $(this).data('mbid');
		var editRow = $('tr[data-mbrowedit="'+rowID+'"]');
		var viewRow = $('tr[data-mbrowview="'+rowID+'"]');

		// show the edit row
		editRow.show();

		// Hide the view row
		viewRow.hide();
		
		e.preventDefault();
	});
	
	// Cancel button click
	$('button.cancel-single-mb').click(function(e) {
		var rowID = $(this).data('rowid');
		var editRow = $('tr[data-mbrowedit="'+rowID+'"]');
		var viewRow = $('tr[data-mbrowview="'+rowID+'"]');

		// show the edit row
		editRow.hide();

		// Hide the view row
		viewRow.show();
		
		e.preventDefault();
	});
	
	// Save button Click
	$('button.edit-single-mb').click(function(e) {
		// clone the edit row into the table
		var formId = $(this).data('form_id');
		var curForm = $('#'+formId);
		var rowId = curForm.data('RowID');
		var resultDiv = $('div#result-'+rowId);
		var fileInp = curForm.find('input[type="file"]');
		
		// If we have a header image, we can't use JS
		if (fileInp.length < 1 || fileInp.size()) {
			return;
		}
		
		// Hide in case there was an update before the fadeout completes
		resultDiv.hide();
		
		var data = curForm.serializeArray();
		data.push({name:'doingAjax',value:'1'});
		
		$.post(
			'/wp-admin/',
			data,
			function(r) {
				var myClass = (r.success) ? 'updated fade' : 'error';
				
				// If we're successful, fade out
				if (r.success) {
					resultDiv.addClass(myClass).html(r.msg).fadeIn().delay(1500).fadeOut(1500);
				}
				// Otherwise keep the error around
				else {
					resultDiv.addClass(myClass).html(r.msg).fadeIn();
				}
			},
			'json'
		);
		e.preventDefault();
	});
	
});