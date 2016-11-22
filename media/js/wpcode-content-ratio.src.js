jQuery(document).ready(function($){
	jQuery("#tabs").tabs();
	jQuery(".stripeMe tr").mouseover(function(){jQuery(this).addClass("over");}).mouseout(function(){jQuery(this).removeClass("over");});
	jQuery(".stripeMe tr:even").addClass("alt");
});