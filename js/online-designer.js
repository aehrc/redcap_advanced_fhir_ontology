function ADVFHIR_ontology_changed(service, category){
  var newSelection = ('ADVFHIR' === service) ? category : '';
  $('#advfhir_ontology_category').val(newSelection);
}
