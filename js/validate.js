function validate(form) {
  var isReview = false;
	for (var i = 0; i < form.elements.length; i++) { 
		var element = form.elements[i];
		if (element.name == 'id' && element.value == '') { 
			alert('The ID field cannot be empty.');
			return false;
		}
		if (element.name == 'firstname' && element.value == '') { 
			alert('The first name field cannot be empty.');
			return false;
		}
		if (element.name == 'lastname' && element.value == '') { 
			alert('The last name field cannot be empty.');
			return false;
		}
		if (element.name == 'email' && !(/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i.test(element.value))) { 
			alert('The email field contains an invalid email address.');
			return false;
		}
		if (element.name == 'reviewmenu') {
		  isReview = true;
		}
	}
	if (isReview) {
	  if(!validateReview(form)) return false;
  }
	if (!validateRadio(form, 'veg', 'Please select a dish type.')) return false;
	if (!validateRadio(form, 'caltype', 'Please select a calendar pattern.')) return false;
	return true;
}

function validateReview(form) {
  for (var i = 0; i < form.elements.length; i++) {
		var element = form.elements[i];
    if (element.name == 'validate[]' && element.value == 2) {
      alert('You have an unrecognized ingredient. Please assign all ingredients to suppliers.');
      return false;
    }
  }
  return true;  
}

function validateRadio(form, name, message) {
  var found = false;
  var valueFound = false;
  for (var i = 0; i < form.elements.length; i++) {
		var element = form.elements[i];
    if (element.name == name) {
      found = true;
      if (element.checked) {
        valueFound = true;
      }
    }
  }
  if (found && !valueFound) {
    alert(message);
    return false;
  }
  return true;
}