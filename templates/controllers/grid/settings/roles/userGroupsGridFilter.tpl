<script type="text/javascript">
	// Attach the form handler to the form.
	$('#userGroupSearchForm').pkpHandler('$.pkp.controllers.form.ClientFormHandler');
</script>
<form class="pkp_form" id="userGroupSearchForm" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.roles.UserGroupGridHandler" op="fetchGrid"}" method="post">
	{formArea id="userGroupSearchFormArea"}
		{fbvElement type="select" id="selectedRoleId" from=$filterData.roleOptions selected=$filterSelectionData.selectedRoleId label="settings.roles.listRoles"}
		{fbvFormButtons id="searchButton" hideCancel="true" submitText="common.search"}
	{/formArea}
</form>