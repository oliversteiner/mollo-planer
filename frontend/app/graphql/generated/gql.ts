/* eslint-disable */
import * as types from './graphql';
import type { TypedDocumentNode as DocumentNode } from '@graphql-typed-document-node/core';

/**
 * Map of all GraphQL operations in the project.
 *
 * This map has several performance disadvantages:
 * 1. It is not tree-shakeable, so it will include all operations in the project.
 * 2. It is not minifiable, so the string of a GraphQL query will be multiple times inside the bundle.
 * 3. It does not support dead code elimination, so it will add unused operations.
 *
 * Therefore it is highly recommended to use the babel or swc plugin for production.
 * Learn more about it here: https://the-guild.dev/graphql/codegen/plugins/presets/preset-client#reducing-bundle-size
 */
type Documents = {
    "\n  query AdminUsers {\n    users {\n      id\n      name\n      email\n      role { key isDefault names { de en } }\n      activity\n      avatarUrl\n      isAgent\n      apiTokens { id name createdAt lastUsedAt expiresAt }\n      twoFactorConfirmed\n      hasPasskey\n      createdAt\n      approved\n      emailVerified\n    }\n  }\n": typeof types.AdminUsersDocument,
    "\n  query AgentProjects {\n    projects {\n      id\n      name\n      viewerRights\n      members { role { key names { de en } } user { id } }\n    }\n  }\n": typeof types.AgentProjectsDocument,
    "\n  mutation ApproveUser($id: ID!) {\n    approveUser(id: $id) { id approved }\n  }\n": typeof types.ApproveUserDocument,
    "\n  mutation RejectUser($id: ID!) {\n    rejectUser(id: $id) { id }\n  }\n": typeof types.RejectUserDocument,
    "\n  mutation CreateUser($input: CreateUserInput!) {\n    createUser(input: $input) { id name }\n  }\n": typeof types.CreateUserDocument,
    "\n  mutation UpdateUser($id: ID!, $input: UpdateUserInput!) {\n    updateUser(id: $id, input: $input) { id name email role { key isDefault names { de en } } activity }\n  }\n": typeof types.UpdateUserDocument,
    "\n  mutation ResetUserTwoFactor($id: ID!) {\n    resetUserTwoFactor(id: $id) { id twoFactorConfirmed hasPasskey }\n  }\n": typeof types.ResetUserTwoFactorDocument,
    "\n  mutation CreateAgentToken($userId: ID!, $name: String!) {\n    createAgentToken(userId: $userId, name: $name) {\n      plainTextToken\n      token { id name }\n    }\n  }\n": typeof types.CreateAgentTokenDocument,
    "\n  mutation RevokeAgentToken($userId: ID!, $tokenId: ID!) {\n    revokeAgentToken(userId: $userId, tokenId: $tokenId) { id }\n  }\n": typeof types.RevokeAgentTokenDocument,
    "\n  mutation DeleteUser($id: ID!) {\n    deleteUser(id: $id) { id }\n  }\n": typeof types.DeleteUserDocument,
    "\n  query AppSettings {\n    appSettings { twoFactorEnabled twoFactorSwitchable projectCreatorRole { key names { de en } } }\n  }\n": typeof types.AppSettingsDocument,
    "\n  mutation SetProjectCreatorRole($key: String!) {\n    setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key names { de en } } }\n  }\n": typeof types.SetProjectCreatorRoleDocument,
    "\n  mutation UpdateAppSettings($twoFactorEnabled: Boolean!) {\n    updateAppSettings(twoFactorEnabled: $twoFactorEnabled) { twoFactorEnabled twoFactorSwitchable }\n  }\n": typeof types.UpdateAppSettingsDocument,
    "\n  query OAuthClients {\n    oauthClients { id name redirectUris confidential revoked selfRegistered createdAt }\n  }\n": typeof types.OAuthClientsDocument,
    "\n  mutation CreateOAuthClient($name: String!, $redirectUris: [String!]!, $confidential: Boolean!) {\n    createOAuthClient(name: $name, redirectUris: $redirectUris, confidential: $confidential) {\n      secret\n      client { id name redirectUris confidential revoked selfRegistered createdAt }\n    }\n  }\n": typeof types.CreateOAuthClientDocument,
    "\n  mutation RevokeOAuthClient($id: ID!) {\n    revokeOAuthClient(id: $id) { id revoked }\n  }\n": typeof types.RevokeOAuthClientDocument,
    "\n  query ProjectAiContext($id: ID!) {\n    project(id: $id) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": typeof types.ProjectAiContextDocument,
    "\n  mutation SetProjectAiContext($projectId: ID!, $input: ProjectAiContextInput!) {\n    setProjectAiContext(projectId: $projectId, input: $input) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": typeof types.SetProjectAiContextDocument,
    "\n  mutation UploadProjectAiContextFile($projectId: ID!, $file: Upload!) {\n    uploadProjectAiContextFile(projectId: $projectId, file: $file) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": typeof types.UploadProjectAiContextFileDocument,
    "\n  mutation RemoveProjectAiContextFile($projectId: ID!) {\n    removeProjectAiContextFile(projectId: $projectId) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": typeof types.RemoveProjectAiContextFileDocument,
    "\n  mutation ClearProjectAiContext($projectId: ID!) {\n    clearProjectAiContext(projectId: $projectId) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": typeof types.ClearProjectAiContextDocument,
    "\n  mutation DraftPlan($messages: [PlanChatMessageInput!]!, $projectId: ID, $templateId: ID, $targetKind: PlanTargetKind, $currentPlan: String, $ideaId: ID) {\n    draftPlan(messages: $messages, projectId: $projectId, templateId: $templateId, targetKind: $targetKind, currentPlan: $currentPlan, ideaId: $ideaId) {\n      reply\n      plan\n      projectId\n      templateId\n      hints\n      choices { kind label detail value recommended }\n      preview {\n        ok\n        dryRun\n        projectId\n        changes { kind path id fields title }\n        errors { path kind message }\n      }\n    }\n  }\n": typeof types.DraftPlanDocument,
    "\n  query AssistantProject($id: ID!) {\n    project(id: $id) { id name archivedAt }\n  }\n": typeof types.AssistantProjectDocument,
    "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole { key names { de en } }\n        progress { done total percent }\n      }\n    }\n  }\n": typeof types.DashboardDocument,
    "\n  query Documents($projectId: ID!, $kind: DocumentKind, $search: String) {\n    documents(projectId: $projectId, kind: $kind, search: $search) {\n      id\n      title\n      kind\n      projectAccess\n      viewerAccess\n      revision\n      updatedAt\n      owner { id name avatarUrl isAgent }\n      updatedBy { id name }\n    }\n  }\n": typeof types.DocumentsDocument,
    "\n  query Document($id: ID!) {\n    document(id: $id) {\n      id\n      title\n      kind\n      content\n      contentJson\n      projectAccess\n      viewerAccess\n      revision\n      createdAt\n      updatedAt\n      deletionId\n      project { id name }\n      owner { id name avatarUrl isAgent }\n      updatedBy { id name }\n      shares { user { id name avatarUrl isAgent } access }\n    }\n  }\n": typeof types.DocumentDocument,
    "\n  mutation CreateDocument($input: CreateDocumentInput!) {\n    createDocument(input: $input) { id revision }\n  }\n": typeof types.CreateDocumentDocument,
    "\n  mutation UpdateDocument($id: ID!, $revision: Int!, $input: UpdateDocumentInput!) {\n    updateDocument(id: $id, revision: $revision, input: $input) {\n      id\n      title\n      kind\n      content\n      contentJson\n      revision\n      updatedAt\n      updatedBy { id name }\n    }\n  }\n": typeof types.UpdateDocumentDocument,
    "\n  mutation DeleteDocument($id: ID!) {\n    deleteDocument(id: $id) { id deletionId }\n  }\n": typeof types.DeleteDocumentDocument,
    "\n  mutation SetDocumentSharing($id: ID!, $input: DocumentSharingInput!) {\n    setDocumentSharing(id: $id, input: $input) {\n      id\n      projectAccess\n      viewerAccess\n      shares { user { id name avatarUrl isAgent } access }\n    }\n  }\n": typeof types.SetDocumentSharingDocument,
    "\n  mutation TransferDocument($id: ID!, $ownerId: ID!) {\n    transferDocument(id: $id, ownerId: $ownerId) {\n      id\n      viewerAccess\n      owner { id name avatarUrl isAgent }\n    }\n  }\n": typeof types.TransferDocumentDocument,
    "\n  mutation ExportDocumentPdf($id: ID!) {\n    exportDocumentPdf(id: $id) { fileName mimeType contentBase64 }\n  }\n": typeof types.ExportDocumentPdfDocument,
    "\n  query DocumentVersions($id: ID!) {\n    document(id: $id) {\n      id\n      title\n      revision\n      viewerAccess\n      versions {\n        revision\n        title\n        kind\n        restoredFrom\n        createdAt\n        createdBy { id name }\n      }\n    }\n  }\n": typeof types.DocumentVersionsDocument,
    "\n  query DocumentVersion($documentId: ID!, $revision: Int!) {\n    documentVersion(documentId: $documentId, revision: $revision) {\n      revision\n      title\n      content\n    }\n  }\n": typeof types.DocumentVersionDocument,
    "\n  mutation RestoreDocumentVersion($id: ID!, $revision: Int!) {\n    restoreDocumentVersion(id: $id, revision: $revision) { id revision }\n  }\n": typeof types.RestoreDocumentVersionDocument,
    "\n  query Ideas($status: IdeaStatus, $first: Int!) {\n    ideas(status: $status, first: $first) {\n      data {\n        id\n        title\n        description\n        status\n        draft\n        createdAt\n        adoptedAt\n        viewerCanUpdate\n        project { id name }\n        author { id name }\n        todos { id title project { id } }\n      }\n    }\n  }\n": typeof types.IdeasDocument,
    "\n  mutation CreateIdea($input: CreateIdeaInput!) {\n    createIdea(input: $input) { id title status }\n  }\n": typeof types.CreateIdeaDocument,
    "\n  mutation UpdateIdea($id: ID!, $input: UpdateIdeaInput!) {\n    updateIdea(id: $id, input: $input) { id title description status draft project { id name } }\n  }\n": typeof types.UpdateIdeaDocument,
    "\n  mutation SetIdeaStatus($id: ID!, $status: IdeaStatus!) {\n    setIdeaStatus(id: $id, status: $status) { id status adoptedAt }\n  }\n": typeof types.SetIdeaStatusDocument,
    "\n  mutation DeleteIdea($id: ID!) {\n    deleteIdea(id: $id) { id deletionId }\n  }\n": typeof types.DeleteIdeaDocument,
    "\n  query Templates {\n    viewerRights\n    templates {\n      id\n      key\n      name\n      description\n      locale\n      isBuiltin\n      projectCount\n      viewerCanUpdate\n      viewerCanDelete\n      createdBy { id name }\n      labels { key name names { locale name } category categoryLabel color }\n      outline { title version todos { title tasks { title labels } } }\n      file\n    }\n  }\n": typeof types.TemplatesDocument,
    "\n  mutation CreateTemplate($input: TemplateInput!) {\n    createTemplate(input: $input) { id name }\n  }\n": typeof types.CreateTemplateDocument,
    "\n  mutation DuplicateTemplate($id: ID!) {\n    duplicateTemplate(id: $id) { id name }\n  }\n": typeof types.DuplicateTemplateDocument,
    "\n  mutation UpdateTemplate($id: ID!, $input: UpdateTemplateInput!) {\n    updateTemplate(id: $id, input: $input) { id name }\n  }\n": typeof types.UpdateTemplateDocument,
    "\n  mutation DeleteTemplate($id: ID!) {\n    deleteTemplate(id: $id)\n  }\n": typeof types.DeleteTemplateDocument,
    "\n  query ProjectLabels($id: ID!) {\n    project(id: $id) {\n      id\n      locale\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n": typeof types.ProjectLabelsDocument,
    "\n  mutation UpdateProjectLabels($projectId: ID!, $labels: [LabelInput!]!) {\n    updateProjectLabels(projectId: $projectId, labels: $labels) {\n      id\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n": typeof types.UpdateProjectLabelsDocument,
    "\n  mutation ApplyTemplateLabels($projectId: ID!, $templateId: ID!, $mode: TemplateLabelMode!) {\n    applyTemplateLabels(projectId: $projectId, templateId: $templateId, mode: $mode) {\n      id\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n": typeof types.ApplyTemplateLabelsDocument,
    "\n  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {\n    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {\n      id\n      labels { id name color position }\n    }\n  }\n": typeof types.SetTaskLabelsDocument,
    "\n  query ExportPlan($projectId: ID!) {\n    exportPlan(projectId: $projectId)\n  }\n": typeof types.ExportPlanDocument,
    "\n  mutation ImportPlan($projectId: ID, $document: String!, $mode: ImportMode, $dryRun: Boolean, $notifyAssignees: Boolean, $templateId: ID, $ideaId: ID, $appendOnly: Boolean) {\n    importPlan(projectId: $projectId, document: $document, mode: $mode, dryRun: $dryRun, notifyAssignees: $notifyAssignees, templateId: $templateId, ideaId: $ideaId, appendOnly: $appendOnly) {\n      ok\n      dryRun\n      projectId\n      changes { kind path id fields title }\n      errors { path kind message }\n    }\n  }\n": typeof types.ImportPlanDocument,
    "\n  mutation CreateTemplateFromProject($projectId: ID!, $name: String!) {\n    createTemplateFromProject(projectId: $projectId, name: $name) { id name }\n  }\n": typeof types.CreateTemplateFromProjectDocument,
    "\n  mutation CreateTemplateFromFile($document: String!) {\n    createTemplateFromFile(document: $document) { id name }\n  }\n": typeof types.CreateTemplateFromFileDocument,
    "\n  query ProjectWebhook($id: ID!) {\n    project(id: $id) {\n      id\n      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }\n    }\n  }\n": typeof types.ProjectWebhookDocument,
    "\n  mutation SetProjectWebhook($projectId: ID!, $input: ProjectWebhookInput!) {\n    setProjectWebhook(projectId: $projectId, input: $input) {\n      id\n      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }\n    }\n  }\n": typeof types.SetProjectWebhookDocument,
    "\n  mutation RemoveProjectWebhook($projectId: ID!) {\n    removeProjectWebhook(projectId: $projectId) { id webhook { kind } }\n  }\n": typeof types.RemoveProjectWebhookDocument,
    "\n  mutation TestProjectWebhook($projectId: ID!) {\n    testProjectWebhook(projectId: $projectId) { ok message }\n  }\n": typeof types.TestProjectWebhookDocument,
    "\n  query MyTasks($status: TaskStatus) {\n    myTasks(status: $status) {\n      id\n      title\n      icon\n      status\n      dueDate\n      userStory { role goal benefit }\n      commentCount\n      description\n      assignee { id name }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n    }\n  }\n": typeof types.MyTasksDocument,
    "\n  mutation UpdateMyNotifications($notifyByEmail: Boolean!) {\n    updateMyNotifications(notifyByEmail: $notifyByEmail) { id notifyByEmail }\n  }\n": typeof types.UpdateMyNotificationsDocument,
    "\n  mutation UpdateMyAvatar($avatar: Upload!) {\n    updateMyAvatar(avatar: $avatar) { id avatarUrl }\n  }\n": typeof types.UpdateMyAvatarDocument,
    "\n  mutation DeleteUserAvatar($id: ID!) {\n    deleteUserAvatar(id: $id) { id avatarUrl }\n  }\n": typeof types.DeleteUserAvatarDocument,
    "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status date }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n": typeof types.ProjectOverviewDocument,
    "\n  query ProjectTimeline($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status date }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n          tasks {\n            id\n            title\n            description\n            icon\n            status\n            dueDate\n            userStory { role goal benefit }\n            commentCount\n            labels { id name color position }\n            assignee { id name avatarUrl }\n            viewerCanUpdate\n            viewerCanDelete\n          }\n        }\n      }\n    }\n  }\n": typeof types.ProjectTimelineDocument,
    "\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      description\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n": typeof types.ProjectTodosDocument,
    "\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n": typeof types.CreateMilestoneDocument,
    "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status date } }\n  }\n": typeof types.UpdateMilestoneDocument,
    "\n  mutation MoveMilestone($id: ID!, $position: Int!) {\n    moveMilestone(id: $id, position: $position) { id }\n  }\n": typeof types.MoveMilestoneDocument,
    "\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id deletionId }\n  }\n": typeof types.DeleteMilestoneDocument,
    "\n  mutation CreateTodo($input: CreateTodoInput!) {\n    createTodo(input: $input) { id }\n  }\n": typeof types.CreateTodoDocument,
    "\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title description icon dueDate }\n  }\n": typeof types.UpdateTodoDocument,
    "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id deletionId }\n  }\n": typeof types.DeleteTodoDocument,
    "\n  query Projects($archived: Boolean = false) {\n    projects(archived: $archived) {\n      id\n      name\n      description\n      viewerRole { key names { de en } }\n      progress { done total percent }\n      counts { milestones todos tasks }\n      members {\n        role { key names { de en } }\n        user { id name avatarUrl }\n      }\n    }\n  }\n": typeof types.ProjectsDocument,
    "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n      name\n      description\n      icon\n      archivedAt\n      viewerRole { key names { de en } }\n      viewerRights\n      labels { id name category color position }\n    }\n  }\n": typeof types.ProjectHeaderDocument,
    "\n  query ProjectRevision($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n    }\n  }\n": typeof types.ProjectRevisionDocument,
    "\n  mutation CreateProject($input: CreateProjectInput!) {\n    createProject(input: $input) {\n      id\n      name\n    }\n  }\n": typeof types.CreateProjectDocument,
    "\n  query SidebarProjects {\n    projects { id name icon }\n  }\n": typeof types.SidebarProjectsDocument,
    "\n  query PermissionMatrix {\n    permissionMatrix {\n      system { right roles { key } }\n      project { right roles { key } }\n    }\n  }\n": typeof types.PermissionMatrixDocument,
    "\n  query Roles($scope: RoleScope) {\n    roles(scope: $scope) {\n      id\n      key\n      scope\n      isDefault\n      names { de en }\n      rights\n    }\n  }\n": typeof types.RolesDocument,
    "\n  query RoleSettings {\n    roles {\n      id\n      key\n      scope\n      isDefault\n      isStandard\n      names { de en }\n      functions { id key names { de en } }\n      projectCap\n      usageCount\n    }\n    roleFunctions {\n      id\n      key\n      scope\n      isStandard\n      names { de en }\n      rights\n      usageCount\n    }\n    systemRights: rights(scope: SYSTEM)\n    projectRights: rights(scope: PROJECT)\n  }\n": typeof types.RoleSettingsDocument,
    "\n  mutation CreateRole($input: CreateRoleInput!) {\n    createRole(input: $input) { id key names { de en } }\n  }\n": typeof types.CreateRoleDocument,
    "\n  mutation UpdateRole($id: ID!, $input: UpdateRoleInput!) {\n    updateRole(id: $id, input: $input) { id key names { de en } }\n  }\n": typeof types.UpdateRoleDocument,
    "\n  mutation DuplicateRole($id: ID!) {\n    duplicateRole(id: $id) { id key names { de en } }\n  }\n": typeof types.DuplicateRoleDocument,
    "\n  mutation DeleteRole($id: ID!) {\n    deleteRole(id: $id) { id }\n  }\n": typeof types.DeleteRoleDocument,
    "\n  mutation SetDefaultRole($id: ID!) {\n    setDefaultRole(id: $id) { id key names { de en } }\n  }\n": typeof types.SetDefaultRoleDocument,
    "\n  mutation ResetRole($id: ID!) {\n    resetRole(id: $id) { id key names { de en } }\n  }\n": typeof types.ResetRoleDocument,
    "\n  mutation CreateRoleFunction($input: CreateRoleFunctionInput!) {\n    createRoleFunction(input: $input) { id key names { de en } }\n  }\n": typeof types.CreateRoleFunctionDocument,
    "\n  mutation UpdateRoleFunction($id: ID!, $input: UpdateRoleFunctionInput!) {\n    updateRoleFunction(id: $id, input: $input) { id key names { de en } }\n  }\n": typeof types.UpdateRoleFunctionDocument,
    "\n  mutation DuplicateRoleFunction($id: ID!) {\n    duplicateRoleFunction(id: $id) { id key names { de en } }\n  }\n": typeof types.DuplicateRoleFunctionDocument,
    "\n  mutation DeleteRoleFunction($id: ID!) {\n    deleteRoleFunction(id: $id) { id }\n  }\n": typeof types.DeleteRoleFunctionDocument,
    "\n  mutation ResetRoleFunction($id: ID!) {\n    resetRoleFunction(id: $id) { id key names { de en } }\n  }\n": typeof types.ResetRoleFunctionDocument,
    "\n  query Security {\n    me {\n      id\n      twoFactorConfirmed\n      hasPasskey\n      passkeys {\n        id\n        name\n        createdAt\n        lastUsedAt\n      }\n    }\n  }\n": typeof types.SecurityDocument,
    "\n  query ApiTokens {\n    me {\n      id\n      apiTokens { id name createdAt lastUsedAt expiresAt }\n    }\n  }\n": typeof types.ApiTokensDocument,
    "\n  mutation CreateApiToken($name: String!) {\n    createApiToken(name: $name) {\n      plainTextToken\n      token { id name }\n    }\n  }\n": typeof types.CreateApiTokenDocument,
    "\n  mutation DeleteApiToken($id: ID!) {\n    deleteApiToken(id: $id) { id }\n  }\n": typeof types.DeleteApiTokenDocument,
    "\n  query ProjectSettings($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      icon\n      locale\n      members {\n        role { key names { de en } }\n        user { id name email avatarUrl isAgent }\n      }\n    }\n  }\n": typeof types.ProjectSettingsDocument,
    "\n  query MemberCandidates($projectId: ID) {\n    memberCandidates(projectId: $projectId) { id name avatarUrl isMember isAgent }\n  }\n": typeof types.MemberCandidatesDocument,
    "\n  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {\n    updateProject(id: $id, input: $input) { id name description icon locale }\n  }\n": typeof types.UpdateProjectDocument,
    "\n  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {\n    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl isAgent } }\n    }\n  }\n": typeof types.SetProjectMemberDocument,
    "\n  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {\n    removeProjectMember(projectId: $projectId, userId: $userId) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl isAgent } }\n    }\n  }\n": typeof types.RemoveProjectMemberDocument,
    "\n  mutation DeleteProject($id: ID!) {\n    deleteProject(id: $id) { id deletionId }\n  }\n": typeof types.DeleteProjectDocument,
    "\n  mutation ArchiveProject($id: ID!) {\n    archiveProject(id: $id) { id archivedAt viewerRights }\n  }\n": typeof types.ArchiveProjectDocument,
    "\n  mutation UnarchiveProject($id: ID!) {\n    unarchiveProject(id: $id) { id archivedAt viewerRights }\n  }\n": typeof types.UnarchiveProjectDocument,
    "\n  query ProjectStatistics($projectId: ID!) {\n    projectStatistics(projectId: $projectId) {\n      milestones { total released planned unversioned }\n      todos\n      tasks\n      progress { total done percent }\n      tasksByStatus { status count percent }\n      tasksByLabel { label { id name color position } count }\n      tasksByPerson { user { id name avatarUrl isAgent } open done }\n      due { overdue soon undated }\n      perMilestone {\n        milestone { id title status release { version status date } }\n        todos\n        tasks\n        progress { total done percent }\n      }\n      doneByWeek { weekStart count }\n      withCommit\n      comments\n    }\n  }\n": typeof types.ProjectStatisticsDocument,
    "\n  query Kanban($projectId: ID!) {\n    kanban(projectId: $projectId) {\n      status\n      tasks {\n        id\n        title\n        icon\n        status\n        boardPosition\n        dueDate\n        commit\n        userStory { role goal benefit }\n        commentCount\n        labels { id name color position }\n        assignee { id name avatarUrl }\n        todo { id title }\n        viewerCanUpdate\n        viewerCanDelete\n      }\n    }\n  }\n": typeof types.KanbanDocument,
    "\n  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {\n    moveTaskToTodo(id: $id, todoId: $todoId) { id }\n  }\n": typeof types.MoveTaskToTodoDocument,
    "\n  mutation MoveTaskInTodo($id: ID!, $position: Int!) {\n    moveTaskInTodo(id: $id, position: $position) { id }\n  }\n": typeof types.MoveTaskInTodoDocument,
    "\n  query ProjectMembers($id: ID!) {\n    project(id: $id) {\n      id\n      members {\n        role { key }\n        user { id name avatarUrl isAgent }\n      }\n    }\n  }\n": typeof types.ProjectMembersDocument,
    "\n  query TaskDetail($id: ID!) {\n    task(id: $id) {\n      id\n      title\n      description\n      icon\n      status\n      boardPosition\n      dueDate\n      commit\n      userStory { role goal benefit }\n      assignee { id name avatarUrl }\n      labels { id name color position }\n      viewerCanUpdate\n      viewerCanAssign\n      viewerCanAssignSelf\n      viewerCanDelete\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name labels { id name category color position } }\n        }\n      }\n      comments {\n        id\n        body\n        createdAt\n        editedAt\n        viewerCanEdit\n        viewerCanDelete\n        author { id name avatarUrl isAgent }\n      }\n    }\n  }\n": typeof types.TaskDetailDocument,
    "\n  mutation CreateTask($input: CreateTaskInput!) {\n    createTask(input: $input) { id }\n  }\n": typeof types.CreateTaskDocument,
    "\n  mutation UpdateTask($id: ID!, $input: UpdateTaskInput!) {\n    updateTask(id: $id, input: $input) { id title description icon dueDate userStory { role goal benefit } }\n  }\n": typeof types.UpdateTaskDocument,
    "\n  mutation MoveTask($id: ID!, $status: TaskStatus!, $position: Int!) {\n    moveTask(id: $id, status: $status, position: $position) { id status boardPosition }\n  }\n": typeof types.MoveTaskDocument,
    "\n  mutation AssignTask($id: ID!, $assigneeId: ID) {\n    assignTask(id: $id, assigneeId: $assigneeId) { id assignee { id name avatarUrl } }\n  }\n": typeof types.AssignTaskDocument,
    "\n  mutation DeleteTask($id: ID!) {\n    deleteTask(id: $id) { id deletionId }\n  }\n": typeof types.DeleteTaskDocument,
    "\n  mutation AddComment($input: AddCommentInput!) {\n    addComment(input: $input) { id }\n  }\n": typeof types.AddCommentDocument,
    "\n  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {\n    updateComment(id: $id, input: $input) { id body editedAt }\n  }\n": typeof types.UpdateCommentDocument,
    "\n  mutation DeleteComment($id: ID!) {\n    deleteComment(id: $id) { id deletionId }\n  }\n": typeof types.DeleteCommentDocument,
    "\n  query Trash($projectId: ID) {\n    trash(projectId: $projectId) {\n      deletionId\n      type\n      id\n      title\n      deletedAt\n      deletedBy { id name avatarUrl }\n      project { id name icon }\n      childCount\n      childCounts { type count }\n      viewerCanRestore\n      viewerCanPurge\n    }\n  }\n": typeof types.TrashDocument,
    "\n  mutation RestoreFromTrash($deletionId: ID!) {\n    restoreFromTrash(deletionId: $deletionId) { deletionId type id title }\n  }\n": typeof types.RestoreFromTrashDocument,
    "\n  mutation PurgeFromTrash($deletionId: ID!) {\n    purgeFromTrash(deletionId: $deletionId)\n  }\n": typeof types.PurgeFromTrashDocument,
    "\n  mutation EmptyProjectTrash($projectId: ID!) {\n    emptyProjectTrash(projectId: $projectId)\n  }\n": typeof types.EmptyProjectTrashDocument,
    "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      role { key names { de en } }\n      activity\n      avatarUrl\n      notifyByEmail\n      approved\n      emailVerified\n    }\n    appSettings {\n      twoFactorEnabled\n      isTestEnvironment\n    }\n    viewerRights\n  }\n": typeof types.ViewerDocument,
    "\n  query ZenProject($id: ID!) {\n    project(id: $id) { id name }\n  }\n": typeof types.ZenProjectDocument,
};
const documents: Documents = {
    "\n  query AdminUsers {\n    users {\n      id\n      name\n      email\n      role { key isDefault names { de en } }\n      activity\n      avatarUrl\n      isAgent\n      apiTokens { id name createdAt lastUsedAt expiresAt }\n      twoFactorConfirmed\n      hasPasskey\n      createdAt\n      approved\n      emailVerified\n    }\n  }\n": types.AdminUsersDocument,
    "\n  query AgentProjects {\n    projects {\n      id\n      name\n      viewerRights\n      members { role { key names { de en } } user { id } }\n    }\n  }\n": types.AgentProjectsDocument,
    "\n  mutation ApproveUser($id: ID!) {\n    approveUser(id: $id) { id approved }\n  }\n": types.ApproveUserDocument,
    "\n  mutation RejectUser($id: ID!) {\n    rejectUser(id: $id) { id }\n  }\n": types.RejectUserDocument,
    "\n  mutation CreateUser($input: CreateUserInput!) {\n    createUser(input: $input) { id name }\n  }\n": types.CreateUserDocument,
    "\n  mutation UpdateUser($id: ID!, $input: UpdateUserInput!) {\n    updateUser(id: $id, input: $input) { id name email role { key isDefault names { de en } } activity }\n  }\n": types.UpdateUserDocument,
    "\n  mutation ResetUserTwoFactor($id: ID!) {\n    resetUserTwoFactor(id: $id) { id twoFactorConfirmed hasPasskey }\n  }\n": types.ResetUserTwoFactorDocument,
    "\n  mutation CreateAgentToken($userId: ID!, $name: String!) {\n    createAgentToken(userId: $userId, name: $name) {\n      plainTextToken\n      token { id name }\n    }\n  }\n": types.CreateAgentTokenDocument,
    "\n  mutation RevokeAgentToken($userId: ID!, $tokenId: ID!) {\n    revokeAgentToken(userId: $userId, tokenId: $tokenId) { id }\n  }\n": types.RevokeAgentTokenDocument,
    "\n  mutation DeleteUser($id: ID!) {\n    deleteUser(id: $id) { id }\n  }\n": types.DeleteUserDocument,
    "\n  query AppSettings {\n    appSettings { twoFactorEnabled twoFactorSwitchable projectCreatorRole { key names { de en } } }\n  }\n": types.AppSettingsDocument,
    "\n  mutation SetProjectCreatorRole($key: String!) {\n    setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key names { de en } } }\n  }\n": types.SetProjectCreatorRoleDocument,
    "\n  mutation UpdateAppSettings($twoFactorEnabled: Boolean!) {\n    updateAppSettings(twoFactorEnabled: $twoFactorEnabled) { twoFactorEnabled twoFactorSwitchable }\n  }\n": types.UpdateAppSettingsDocument,
    "\n  query OAuthClients {\n    oauthClients { id name redirectUris confidential revoked selfRegistered createdAt }\n  }\n": types.OAuthClientsDocument,
    "\n  mutation CreateOAuthClient($name: String!, $redirectUris: [String!]!, $confidential: Boolean!) {\n    createOAuthClient(name: $name, redirectUris: $redirectUris, confidential: $confidential) {\n      secret\n      client { id name redirectUris confidential revoked selfRegistered createdAt }\n    }\n  }\n": types.CreateOAuthClientDocument,
    "\n  mutation RevokeOAuthClient($id: ID!) {\n    revokeOAuthClient(id: $id) { id revoked }\n  }\n": types.RevokeOAuthClientDocument,
    "\n  query ProjectAiContext($id: ID!) {\n    project(id: $id) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": types.ProjectAiContextDocument,
    "\n  mutation SetProjectAiContext($projectId: ID!, $input: ProjectAiContextInput!) {\n    setProjectAiContext(projectId: $projectId, input: $input) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": types.SetProjectAiContextDocument,
    "\n  mutation UploadProjectAiContextFile($projectId: ID!, $file: Upload!) {\n    uploadProjectAiContextFile(projectId: $projectId, file: $file) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": types.UploadProjectAiContextFileDocument,
    "\n  mutation RemoveProjectAiContextFile($projectId: ID!) {\n    removeProjectAiContextFile(projectId: $projectId) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": types.RemoveProjectAiContextFileDocument,
    "\n  mutation ClearProjectAiContext($projectId: ID!) {\n    clearProjectAiContext(projectId: $projectId) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n": types.ClearProjectAiContextDocument,
    "\n  mutation DraftPlan($messages: [PlanChatMessageInput!]!, $projectId: ID, $templateId: ID, $targetKind: PlanTargetKind, $currentPlan: String, $ideaId: ID) {\n    draftPlan(messages: $messages, projectId: $projectId, templateId: $templateId, targetKind: $targetKind, currentPlan: $currentPlan, ideaId: $ideaId) {\n      reply\n      plan\n      projectId\n      templateId\n      hints\n      choices { kind label detail value recommended }\n      preview {\n        ok\n        dryRun\n        projectId\n        changes { kind path id fields title }\n        errors { path kind message }\n      }\n    }\n  }\n": types.DraftPlanDocument,
    "\n  query AssistantProject($id: ID!) {\n    project(id: $id) { id name archivedAt }\n  }\n": types.AssistantProjectDocument,
    "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole { key names { de en } }\n        progress { done total percent }\n      }\n    }\n  }\n": types.DashboardDocument,
    "\n  query Documents($projectId: ID!, $kind: DocumentKind, $search: String) {\n    documents(projectId: $projectId, kind: $kind, search: $search) {\n      id\n      title\n      kind\n      projectAccess\n      viewerAccess\n      revision\n      updatedAt\n      owner { id name avatarUrl isAgent }\n      updatedBy { id name }\n    }\n  }\n": types.DocumentsDocument,
    "\n  query Document($id: ID!) {\n    document(id: $id) {\n      id\n      title\n      kind\n      content\n      contentJson\n      projectAccess\n      viewerAccess\n      revision\n      createdAt\n      updatedAt\n      deletionId\n      project { id name }\n      owner { id name avatarUrl isAgent }\n      updatedBy { id name }\n      shares { user { id name avatarUrl isAgent } access }\n    }\n  }\n": types.DocumentDocument,
    "\n  mutation CreateDocument($input: CreateDocumentInput!) {\n    createDocument(input: $input) { id revision }\n  }\n": types.CreateDocumentDocument,
    "\n  mutation UpdateDocument($id: ID!, $revision: Int!, $input: UpdateDocumentInput!) {\n    updateDocument(id: $id, revision: $revision, input: $input) {\n      id\n      title\n      kind\n      content\n      contentJson\n      revision\n      updatedAt\n      updatedBy { id name }\n    }\n  }\n": types.UpdateDocumentDocument,
    "\n  mutation DeleteDocument($id: ID!) {\n    deleteDocument(id: $id) { id deletionId }\n  }\n": types.DeleteDocumentDocument,
    "\n  mutation SetDocumentSharing($id: ID!, $input: DocumentSharingInput!) {\n    setDocumentSharing(id: $id, input: $input) {\n      id\n      projectAccess\n      viewerAccess\n      shares { user { id name avatarUrl isAgent } access }\n    }\n  }\n": types.SetDocumentSharingDocument,
    "\n  mutation TransferDocument($id: ID!, $ownerId: ID!) {\n    transferDocument(id: $id, ownerId: $ownerId) {\n      id\n      viewerAccess\n      owner { id name avatarUrl isAgent }\n    }\n  }\n": types.TransferDocumentDocument,
    "\n  mutation ExportDocumentPdf($id: ID!) {\n    exportDocumentPdf(id: $id) { fileName mimeType contentBase64 }\n  }\n": types.ExportDocumentPdfDocument,
    "\n  query DocumentVersions($id: ID!) {\n    document(id: $id) {\n      id\n      title\n      revision\n      viewerAccess\n      versions {\n        revision\n        title\n        kind\n        restoredFrom\n        createdAt\n        createdBy { id name }\n      }\n    }\n  }\n": types.DocumentVersionsDocument,
    "\n  query DocumentVersion($documentId: ID!, $revision: Int!) {\n    documentVersion(documentId: $documentId, revision: $revision) {\n      revision\n      title\n      content\n    }\n  }\n": types.DocumentVersionDocument,
    "\n  mutation RestoreDocumentVersion($id: ID!, $revision: Int!) {\n    restoreDocumentVersion(id: $id, revision: $revision) { id revision }\n  }\n": types.RestoreDocumentVersionDocument,
    "\n  query Ideas($status: IdeaStatus, $first: Int!) {\n    ideas(status: $status, first: $first) {\n      data {\n        id\n        title\n        description\n        status\n        draft\n        createdAt\n        adoptedAt\n        viewerCanUpdate\n        project { id name }\n        author { id name }\n        todos { id title project { id } }\n      }\n    }\n  }\n": types.IdeasDocument,
    "\n  mutation CreateIdea($input: CreateIdeaInput!) {\n    createIdea(input: $input) { id title status }\n  }\n": types.CreateIdeaDocument,
    "\n  mutation UpdateIdea($id: ID!, $input: UpdateIdeaInput!) {\n    updateIdea(id: $id, input: $input) { id title description status draft project { id name } }\n  }\n": types.UpdateIdeaDocument,
    "\n  mutation SetIdeaStatus($id: ID!, $status: IdeaStatus!) {\n    setIdeaStatus(id: $id, status: $status) { id status adoptedAt }\n  }\n": types.SetIdeaStatusDocument,
    "\n  mutation DeleteIdea($id: ID!) {\n    deleteIdea(id: $id) { id deletionId }\n  }\n": types.DeleteIdeaDocument,
    "\n  query Templates {\n    viewerRights\n    templates {\n      id\n      key\n      name\n      description\n      locale\n      isBuiltin\n      projectCount\n      viewerCanUpdate\n      viewerCanDelete\n      createdBy { id name }\n      labels { key name names { locale name } category categoryLabel color }\n      outline { title version todos { title tasks { title labels } } }\n      file\n    }\n  }\n": types.TemplatesDocument,
    "\n  mutation CreateTemplate($input: TemplateInput!) {\n    createTemplate(input: $input) { id name }\n  }\n": types.CreateTemplateDocument,
    "\n  mutation DuplicateTemplate($id: ID!) {\n    duplicateTemplate(id: $id) { id name }\n  }\n": types.DuplicateTemplateDocument,
    "\n  mutation UpdateTemplate($id: ID!, $input: UpdateTemplateInput!) {\n    updateTemplate(id: $id, input: $input) { id name }\n  }\n": types.UpdateTemplateDocument,
    "\n  mutation DeleteTemplate($id: ID!) {\n    deleteTemplate(id: $id)\n  }\n": types.DeleteTemplateDocument,
    "\n  query ProjectLabels($id: ID!) {\n    project(id: $id) {\n      id\n      locale\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n": types.ProjectLabelsDocument,
    "\n  mutation UpdateProjectLabels($projectId: ID!, $labels: [LabelInput!]!) {\n    updateProjectLabels(projectId: $projectId, labels: $labels) {\n      id\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n": types.UpdateProjectLabelsDocument,
    "\n  mutation ApplyTemplateLabels($projectId: ID!, $templateId: ID!, $mode: TemplateLabelMode!) {\n    applyTemplateLabels(projectId: $projectId, templateId: $templateId, mode: $mode) {\n      id\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n": types.ApplyTemplateLabelsDocument,
    "\n  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {\n    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {\n      id\n      labels { id name color position }\n    }\n  }\n": types.SetTaskLabelsDocument,
    "\n  query ExportPlan($projectId: ID!) {\n    exportPlan(projectId: $projectId)\n  }\n": types.ExportPlanDocument,
    "\n  mutation ImportPlan($projectId: ID, $document: String!, $mode: ImportMode, $dryRun: Boolean, $notifyAssignees: Boolean, $templateId: ID, $ideaId: ID, $appendOnly: Boolean) {\n    importPlan(projectId: $projectId, document: $document, mode: $mode, dryRun: $dryRun, notifyAssignees: $notifyAssignees, templateId: $templateId, ideaId: $ideaId, appendOnly: $appendOnly) {\n      ok\n      dryRun\n      projectId\n      changes { kind path id fields title }\n      errors { path kind message }\n    }\n  }\n": types.ImportPlanDocument,
    "\n  mutation CreateTemplateFromProject($projectId: ID!, $name: String!) {\n    createTemplateFromProject(projectId: $projectId, name: $name) { id name }\n  }\n": types.CreateTemplateFromProjectDocument,
    "\n  mutation CreateTemplateFromFile($document: String!) {\n    createTemplateFromFile(document: $document) { id name }\n  }\n": types.CreateTemplateFromFileDocument,
    "\n  query ProjectWebhook($id: ID!) {\n    project(id: $id) {\n      id\n      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }\n    }\n  }\n": types.ProjectWebhookDocument,
    "\n  mutation SetProjectWebhook($projectId: ID!, $input: ProjectWebhookInput!) {\n    setProjectWebhook(projectId: $projectId, input: $input) {\n      id\n      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }\n    }\n  }\n": types.SetProjectWebhookDocument,
    "\n  mutation RemoveProjectWebhook($projectId: ID!) {\n    removeProjectWebhook(projectId: $projectId) { id webhook { kind } }\n  }\n": types.RemoveProjectWebhookDocument,
    "\n  mutation TestProjectWebhook($projectId: ID!) {\n    testProjectWebhook(projectId: $projectId) { ok message }\n  }\n": types.TestProjectWebhookDocument,
    "\n  query MyTasks($status: TaskStatus) {\n    myTasks(status: $status) {\n      id\n      title\n      icon\n      status\n      dueDate\n      userStory { role goal benefit }\n      commentCount\n      description\n      assignee { id name }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n    }\n  }\n": types.MyTasksDocument,
    "\n  mutation UpdateMyNotifications($notifyByEmail: Boolean!) {\n    updateMyNotifications(notifyByEmail: $notifyByEmail) { id notifyByEmail }\n  }\n": types.UpdateMyNotificationsDocument,
    "\n  mutation UpdateMyAvatar($avatar: Upload!) {\n    updateMyAvatar(avatar: $avatar) { id avatarUrl }\n  }\n": types.UpdateMyAvatarDocument,
    "\n  mutation DeleteUserAvatar($id: ID!) {\n    deleteUserAvatar(id: $id) { id avatarUrl }\n  }\n": types.DeleteUserAvatarDocument,
    "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status date }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n": types.ProjectOverviewDocument,
    "\n  query ProjectTimeline($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status date }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n          tasks {\n            id\n            title\n            description\n            icon\n            status\n            dueDate\n            userStory { role goal benefit }\n            commentCount\n            labels { id name color position }\n            assignee { id name avatarUrl }\n            viewerCanUpdate\n            viewerCanDelete\n          }\n        }\n      }\n    }\n  }\n": types.ProjectTimelineDocument,
    "\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      description\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n": types.ProjectTodosDocument,
    "\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n": types.CreateMilestoneDocument,
    "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status date } }\n  }\n": types.UpdateMilestoneDocument,
    "\n  mutation MoveMilestone($id: ID!, $position: Int!) {\n    moveMilestone(id: $id, position: $position) { id }\n  }\n": types.MoveMilestoneDocument,
    "\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id deletionId }\n  }\n": types.DeleteMilestoneDocument,
    "\n  mutation CreateTodo($input: CreateTodoInput!) {\n    createTodo(input: $input) { id }\n  }\n": types.CreateTodoDocument,
    "\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title description icon dueDate }\n  }\n": types.UpdateTodoDocument,
    "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id deletionId }\n  }\n": types.DeleteTodoDocument,
    "\n  query Projects($archived: Boolean = false) {\n    projects(archived: $archived) {\n      id\n      name\n      description\n      viewerRole { key names { de en } }\n      progress { done total percent }\n      counts { milestones todos tasks }\n      members {\n        role { key names { de en } }\n        user { id name avatarUrl }\n      }\n    }\n  }\n": types.ProjectsDocument,
    "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n      name\n      description\n      icon\n      archivedAt\n      viewerRole { key names { de en } }\n      viewerRights\n      labels { id name category color position }\n    }\n  }\n": types.ProjectHeaderDocument,
    "\n  query ProjectRevision($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n    }\n  }\n": types.ProjectRevisionDocument,
    "\n  mutation CreateProject($input: CreateProjectInput!) {\n    createProject(input: $input) {\n      id\n      name\n    }\n  }\n": types.CreateProjectDocument,
    "\n  query SidebarProjects {\n    projects { id name icon }\n  }\n": types.SidebarProjectsDocument,
    "\n  query PermissionMatrix {\n    permissionMatrix {\n      system { right roles { key } }\n      project { right roles { key } }\n    }\n  }\n": types.PermissionMatrixDocument,
    "\n  query Roles($scope: RoleScope) {\n    roles(scope: $scope) {\n      id\n      key\n      scope\n      isDefault\n      names { de en }\n      rights\n    }\n  }\n": types.RolesDocument,
    "\n  query RoleSettings {\n    roles {\n      id\n      key\n      scope\n      isDefault\n      isStandard\n      names { de en }\n      functions { id key names { de en } }\n      projectCap\n      usageCount\n    }\n    roleFunctions {\n      id\n      key\n      scope\n      isStandard\n      names { de en }\n      rights\n      usageCount\n    }\n    systemRights: rights(scope: SYSTEM)\n    projectRights: rights(scope: PROJECT)\n  }\n": types.RoleSettingsDocument,
    "\n  mutation CreateRole($input: CreateRoleInput!) {\n    createRole(input: $input) { id key names { de en } }\n  }\n": types.CreateRoleDocument,
    "\n  mutation UpdateRole($id: ID!, $input: UpdateRoleInput!) {\n    updateRole(id: $id, input: $input) { id key names { de en } }\n  }\n": types.UpdateRoleDocument,
    "\n  mutation DuplicateRole($id: ID!) {\n    duplicateRole(id: $id) { id key names { de en } }\n  }\n": types.DuplicateRoleDocument,
    "\n  mutation DeleteRole($id: ID!) {\n    deleteRole(id: $id) { id }\n  }\n": types.DeleteRoleDocument,
    "\n  mutation SetDefaultRole($id: ID!) {\n    setDefaultRole(id: $id) { id key names { de en } }\n  }\n": types.SetDefaultRoleDocument,
    "\n  mutation ResetRole($id: ID!) {\n    resetRole(id: $id) { id key names { de en } }\n  }\n": types.ResetRoleDocument,
    "\n  mutation CreateRoleFunction($input: CreateRoleFunctionInput!) {\n    createRoleFunction(input: $input) { id key names { de en } }\n  }\n": types.CreateRoleFunctionDocument,
    "\n  mutation UpdateRoleFunction($id: ID!, $input: UpdateRoleFunctionInput!) {\n    updateRoleFunction(id: $id, input: $input) { id key names { de en } }\n  }\n": types.UpdateRoleFunctionDocument,
    "\n  mutation DuplicateRoleFunction($id: ID!) {\n    duplicateRoleFunction(id: $id) { id key names { de en } }\n  }\n": types.DuplicateRoleFunctionDocument,
    "\n  mutation DeleteRoleFunction($id: ID!) {\n    deleteRoleFunction(id: $id) { id }\n  }\n": types.DeleteRoleFunctionDocument,
    "\n  mutation ResetRoleFunction($id: ID!) {\n    resetRoleFunction(id: $id) { id key names { de en } }\n  }\n": types.ResetRoleFunctionDocument,
    "\n  query Security {\n    me {\n      id\n      twoFactorConfirmed\n      hasPasskey\n      passkeys {\n        id\n        name\n        createdAt\n        lastUsedAt\n      }\n    }\n  }\n": types.SecurityDocument,
    "\n  query ApiTokens {\n    me {\n      id\n      apiTokens { id name createdAt lastUsedAt expiresAt }\n    }\n  }\n": types.ApiTokensDocument,
    "\n  mutation CreateApiToken($name: String!) {\n    createApiToken(name: $name) {\n      plainTextToken\n      token { id name }\n    }\n  }\n": types.CreateApiTokenDocument,
    "\n  mutation DeleteApiToken($id: ID!) {\n    deleteApiToken(id: $id) { id }\n  }\n": types.DeleteApiTokenDocument,
    "\n  query ProjectSettings($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      icon\n      locale\n      members {\n        role { key names { de en } }\n        user { id name email avatarUrl isAgent }\n      }\n    }\n  }\n": types.ProjectSettingsDocument,
    "\n  query MemberCandidates($projectId: ID) {\n    memberCandidates(projectId: $projectId) { id name avatarUrl isMember isAgent }\n  }\n": types.MemberCandidatesDocument,
    "\n  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {\n    updateProject(id: $id, input: $input) { id name description icon locale }\n  }\n": types.UpdateProjectDocument,
    "\n  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {\n    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl isAgent } }\n    }\n  }\n": types.SetProjectMemberDocument,
    "\n  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {\n    removeProjectMember(projectId: $projectId, userId: $userId) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl isAgent } }\n    }\n  }\n": types.RemoveProjectMemberDocument,
    "\n  mutation DeleteProject($id: ID!) {\n    deleteProject(id: $id) { id deletionId }\n  }\n": types.DeleteProjectDocument,
    "\n  mutation ArchiveProject($id: ID!) {\n    archiveProject(id: $id) { id archivedAt viewerRights }\n  }\n": types.ArchiveProjectDocument,
    "\n  mutation UnarchiveProject($id: ID!) {\n    unarchiveProject(id: $id) { id archivedAt viewerRights }\n  }\n": types.UnarchiveProjectDocument,
    "\n  query ProjectStatistics($projectId: ID!) {\n    projectStatistics(projectId: $projectId) {\n      milestones { total released planned unversioned }\n      todos\n      tasks\n      progress { total done percent }\n      tasksByStatus { status count percent }\n      tasksByLabel { label { id name color position } count }\n      tasksByPerson { user { id name avatarUrl isAgent } open done }\n      due { overdue soon undated }\n      perMilestone {\n        milestone { id title status release { version status date } }\n        todos\n        tasks\n        progress { total done percent }\n      }\n      doneByWeek { weekStart count }\n      withCommit\n      comments\n    }\n  }\n": types.ProjectStatisticsDocument,
    "\n  query Kanban($projectId: ID!) {\n    kanban(projectId: $projectId) {\n      status\n      tasks {\n        id\n        title\n        icon\n        status\n        boardPosition\n        dueDate\n        commit\n        userStory { role goal benefit }\n        commentCount\n        labels { id name color position }\n        assignee { id name avatarUrl }\n        todo { id title }\n        viewerCanUpdate\n        viewerCanDelete\n      }\n    }\n  }\n": types.KanbanDocument,
    "\n  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {\n    moveTaskToTodo(id: $id, todoId: $todoId) { id }\n  }\n": types.MoveTaskToTodoDocument,
    "\n  mutation MoveTaskInTodo($id: ID!, $position: Int!) {\n    moveTaskInTodo(id: $id, position: $position) { id }\n  }\n": types.MoveTaskInTodoDocument,
    "\n  query ProjectMembers($id: ID!) {\n    project(id: $id) {\n      id\n      members {\n        role { key }\n        user { id name avatarUrl isAgent }\n      }\n    }\n  }\n": types.ProjectMembersDocument,
    "\n  query TaskDetail($id: ID!) {\n    task(id: $id) {\n      id\n      title\n      description\n      icon\n      status\n      boardPosition\n      dueDate\n      commit\n      userStory { role goal benefit }\n      assignee { id name avatarUrl }\n      labels { id name color position }\n      viewerCanUpdate\n      viewerCanAssign\n      viewerCanAssignSelf\n      viewerCanDelete\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name labels { id name category color position } }\n        }\n      }\n      comments {\n        id\n        body\n        createdAt\n        editedAt\n        viewerCanEdit\n        viewerCanDelete\n        author { id name avatarUrl isAgent }\n      }\n    }\n  }\n": types.TaskDetailDocument,
    "\n  mutation CreateTask($input: CreateTaskInput!) {\n    createTask(input: $input) { id }\n  }\n": types.CreateTaskDocument,
    "\n  mutation UpdateTask($id: ID!, $input: UpdateTaskInput!) {\n    updateTask(id: $id, input: $input) { id title description icon dueDate userStory { role goal benefit } }\n  }\n": types.UpdateTaskDocument,
    "\n  mutation MoveTask($id: ID!, $status: TaskStatus!, $position: Int!) {\n    moveTask(id: $id, status: $status, position: $position) { id status boardPosition }\n  }\n": types.MoveTaskDocument,
    "\n  mutation AssignTask($id: ID!, $assigneeId: ID) {\n    assignTask(id: $id, assigneeId: $assigneeId) { id assignee { id name avatarUrl } }\n  }\n": types.AssignTaskDocument,
    "\n  mutation DeleteTask($id: ID!) {\n    deleteTask(id: $id) { id deletionId }\n  }\n": types.DeleteTaskDocument,
    "\n  mutation AddComment($input: AddCommentInput!) {\n    addComment(input: $input) { id }\n  }\n": types.AddCommentDocument,
    "\n  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {\n    updateComment(id: $id, input: $input) { id body editedAt }\n  }\n": types.UpdateCommentDocument,
    "\n  mutation DeleteComment($id: ID!) {\n    deleteComment(id: $id) { id deletionId }\n  }\n": types.DeleteCommentDocument,
    "\n  query Trash($projectId: ID) {\n    trash(projectId: $projectId) {\n      deletionId\n      type\n      id\n      title\n      deletedAt\n      deletedBy { id name avatarUrl }\n      project { id name icon }\n      childCount\n      childCounts { type count }\n      viewerCanRestore\n      viewerCanPurge\n    }\n  }\n": types.TrashDocument,
    "\n  mutation RestoreFromTrash($deletionId: ID!) {\n    restoreFromTrash(deletionId: $deletionId) { deletionId type id title }\n  }\n": types.RestoreFromTrashDocument,
    "\n  mutation PurgeFromTrash($deletionId: ID!) {\n    purgeFromTrash(deletionId: $deletionId)\n  }\n": types.PurgeFromTrashDocument,
    "\n  mutation EmptyProjectTrash($projectId: ID!) {\n    emptyProjectTrash(projectId: $projectId)\n  }\n": types.EmptyProjectTrashDocument,
    "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      role { key names { de en } }\n      activity\n      avatarUrl\n      notifyByEmail\n      approved\n      emailVerified\n    }\n    appSettings {\n      twoFactorEnabled\n      isTestEnvironment\n    }\n    viewerRights\n  }\n": types.ViewerDocument,
    "\n  query ZenProject($id: ID!) {\n    project(id: $id) { id name }\n  }\n": types.ZenProjectDocument,
};

/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 *
 *
 * @example
 * ```ts
 * const query = graphql(`query GetUser($id: ID!) { user(id: $id) { name } }`);
 * ```
 *
 * The query argument is unknown!
 * Please regenerate the types.
 */
export function graphql(source: string): unknown;

/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query AdminUsers {\n    users {\n      id\n      name\n      email\n      role { key isDefault names { de en } }\n      activity\n      avatarUrl\n      isAgent\n      apiTokens { id name createdAt lastUsedAt expiresAt }\n      twoFactorConfirmed\n      hasPasskey\n      createdAt\n      approved\n      emailVerified\n    }\n  }\n"): (typeof documents)["\n  query AdminUsers {\n    users {\n      id\n      name\n      email\n      role { key isDefault names { de en } }\n      activity\n      avatarUrl\n      isAgent\n      apiTokens { id name createdAt lastUsedAt expiresAt }\n      twoFactorConfirmed\n      hasPasskey\n      createdAt\n      approved\n      emailVerified\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query AgentProjects {\n    projects {\n      id\n      name\n      viewerRights\n      members { role { key names { de en } } user { id } }\n    }\n  }\n"): (typeof documents)["\n  query AgentProjects {\n    projects {\n      id\n      name\n      viewerRights\n      members { role { key names { de en } } user { id } }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ApproveUser($id: ID!) {\n    approveUser(id: $id) { id approved }\n  }\n"): (typeof documents)["\n  mutation ApproveUser($id: ID!) {\n    approveUser(id: $id) { id approved }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RejectUser($id: ID!) {\n    rejectUser(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation RejectUser($id: ID!) {\n    rejectUser(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateUser($input: CreateUserInput!) {\n    createUser(input: $input) { id name }\n  }\n"): (typeof documents)["\n  mutation CreateUser($input: CreateUserInput!) {\n    createUser(input: $input) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateUser($id: ID!, $input: UpdateUserInput!) {\n    updateUser(id: $id, input: $input) { id name email role { key isDefault names { de en } } activity }\n  }\n"): (typeof documents)["\n  mutation UpdateUser($id: ID!, $input: UpdateUserInput!) {\n    updateUser(id: $id, input: $input) { id name email role { key isDefault names { de en } } activity }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ResetUserTwoFactor($id: ID!) {\n    resetUserTwoFactor(id: $id) { id twoFactorConfirmed hasPasskey }\n  }\n"): (typeof documents)["\n  mutation ResetUserTwoFactor($id: ID!) {\n    resetUserTwoFactor(id: $id) { id twoFactorConfirmed hasPasskey }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateAgentToken($userId: ID!, $name: String!) {\n    createAgentToken(userId: $userId, name: $name) {\n      plainTextToken\n      token { id name }\n    }\n  }\n"): (typeof documents)["\n  mutation CreateAgentToken($userId: ID!, $name: String!) {\n    createAgentToken(userId: $userId, name: $name) {\n      plainTextToken\n      token { id name }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RevokeAgentToken($userId: ID!, $tokenId: ID!) {\n    revokeAgentToken(userId: $userId, tokenId: $tokenId) { id }\n  }\n"): (typeof documents)["\n  mutation RevokeAgentToken($userId: ID!, $tokenId: ID!) {\n    revokeAgentToken(userId: $userId, tokenId: $tokenId) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteUser($id: ID!) {\n    deleteUser(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteUser($id: ID!) {\n    deleteUser(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query AppSettings {\n    appSettings { twoFactorEnabled twoFactorSwitchable projectCreatorRole { key names { de en } } }\n  }\n"): (typeof documents)["\n  query AppSettings {\n    appSettings { twoFactorEnabled twoFactorSwitchable projectCreatorRole { key names { de en } } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetProjectCreatorRole($key: String!) {\n    setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key names { de en } } }\n  }\n"): (typeof documents)["\n  mutation SetProjectCreatorRole($key: String!) {\n    setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key names { de en } } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateAppSettings($twoFactorEnabled: Boolean!) {\n    updateAppSettings(twoFactorEnabled: $twoFactorEnabled) { twoFactorEnabled twoFactorSwitchable }\n  }\n"): (typeof documents)["\n  mutation UpdateAppSettings($twoFactorEnabled: Boolean!) {\n    updateAppSettings(twoFactorEnabled: $twoFactorEnabled) { twoFactorEnabled twoFactorSwitchable }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query OAuthClients {\n    oauthClients { id name redirectUris confidential revoked selfRegistered createdAt }\n  }\n"): (typeof documents)["\n  query OAuthClients {\n    oauthClients { id name redirectUris confidential revoked selfRegistered createdAt }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateOAuthClient($name: String!, $redirectUris: [String!]!, $confidential: Boolean!) {\n    createOAuthClient(name: $name, redirectUris: $redirectUris, confidential: $confidential) {\n      secret\n      client { id name redirectUris confidential revoked selfRegistered createdAt }\n    }\n  }\n"): (typeof documents)["\n  mutation CreateOAuthClient($name: String!, $redirectUris: [String!]!, $confidential: Boolean!) {\n    createOAuthClient(name: $name, redirectUris: $redirectUris, confidential: $confidential) {\n      secret\n      client { id name redirectUris confidential revoked selfRegistered createdAt }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RevokeOAuthClient($id: ID!) {\n    revokeOAuthClient(id: $id) { id revoked }\n  }\n"): (typeof documents)["\n  mutation RevokeOAuthClient($id: ID!) {\n    revokeOAuthClient(id: $id) { id revoked }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectAiContext($id: ID!) {\n    project(id: $id) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"): (typeof documents)["\n  query ProjectAiContext($id: ID!) {\n    project(id: $id) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetProjectAiContext($projectId: ID!, $input: ProjectAiContextInput!) {\n    setProjectAiContext(projectId: $projectId, input: $input) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"): (typeof documents)["\n  mutation SetProjectAiContext($projectId: ID!, $input: ProjectAiContextInput!) {\n    setProjectAiContext(projectId: $projectId, input: $input) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UploadProjectAiContextFile($projectId: ID!, $file: Upload!) {\n    uploadProjectAiContextFile(projectId: $projectId, file: $file) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"): (typeof documents)["\n  mutation UploadProjectAiContextFile($projectId: ID!, $file: Upload!) {\n    uploadProjectAiContextFile(projectId: $projectId, file: $file) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RemoveProjectAiContextFile($projectId: ID!) {\n    removeProjectAiContextFile(projectId: $projectId) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"): (typeof documents)["\n  mutation RemoveProjectAiContextFile($projectId: ID!) {\n    removeProjectAiContextFile(projectId: $projectId) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ClearProjectAiContext($projectId: ID!) {\n    clearProjectAiContext(projectId: $projectId) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"): (typeof documents)["\n  mutation ClearProjectAiContext($projectId: ID!) {\n    clearProjectAiContext(projectId: $projectId) {\n      id\n      aiContext { text fileName fileText characters sharedWithTools updatedAt }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DraftPlan($messages: [PlanChatMessageInput!]!, $projectId: ID, $templateId: ID, $targetKind: PlanTargetKind, $currentPlan: String, $ideaId: ID) {\n    draftPlan(messages: $messages, projectId: $projectId, templateId: $templateId, targetKind: $targetKind, currentPlan: $currentPlan, ideaId: $ideaId) {\n      reply\n      plan\n      projectId\n      templateId\n      hints\n      choices { kind label detail value recommended }\n      preview {\n        ok\n        dryRun\n        projectId\n        changes { kind path id fields title }\n        errors { path kind message }\n      }\n    }\n  }\n"): (typeof documents)["\n  mutation DraftPlan($messages: [PlanChatMessageInput!]!, $projectId: ID, $templateId: ID, $targetKind: PlanTargetKind, $currentPlan: String, $ideaId: ID) {\n    draftPlan(messages: $messages, projectId: $projectId, templateId: $templateId, targetKind: $targetKind, currentPlan: $currentPlan, ideaId: $ideaId) {\n      reply\n      plan\n      projectId\n      templateId\n      hints\n      choices { kind label detail value recommended }\n      preview {\n        ok\n        dryRun\n        projectId\n        changes { kind path id fields title }\n        errors { path kind message }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query AssistantProject($id: ID!) {\n    project(id: $id) { id name archivedAt }\n  }\n"): (typeof documents)["\n  query AssistantProject($id: ID!) {\n    project(id: $id) { id name archivedAt }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole { key names { de en } }\n        progress { done total percent }\n      }\n    }\n  }\n"): (typeof documents)["\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole { key names { de en } }\n        progress { done total percent }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Documents($projectId: ID!, $kind: DocumentKind, $search: String) {\n    documents(projectId: $projectId, kind: $kind, search: $search) {\n      id\n      title\n      kind\n      projectAccess\n      viewerAccess\n      revision\n      updatedAt\n      owner { id name avatarUrl isAgent }\n      updatedBy { id name }\n    }\n  }\n"): (typeof documents)["\n  query Documents($projectId: ID!, $kind: DocumentKind, $search: String) {\n    documents(projectId: $projectId, kind: $kind, search: $search) {\n      id\n      title\n      kind\n      projectAccess\n      viewerAccess\n      revision\n      updatedAt\n      owner { id name avatarUrl isAgent }\n      updatedBy { id name }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Document($id: ID!) {\n    document(id: $id) {\n      id\n      title\n      kind\n      content\n      contentJson\n      projectAccess\n      viewerAccess\n      revision\n      createdAt\n      updatedAt\n      deletionId\n      project { id name }\n      owner { id name avatarUrl isAgent }\n      updatedBy { id name }\n      shares { user { id name avatarUrl isAgent } access }\n    }\n  }\n"): (typeof documents)["\n  query Document($id: ID!) {\n    document(id: $id) {\n      id\n      title\n      kind\n      content\n      contentJson\n      projectAccess\n      viewerAccess\n      revision\n      createdAt\n      updatedAt\n      deletionId\n      project { id name }\n      owner { id name avatarUrl isAgent }\n      updatedBy { id name }\n      shares { user { id name avatarUrl isAgent } access }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateDocument($input: CreateDocumentInput!) {\n    createDocument(input: $input) { id revision }\n  }\n"): (typeof documents)["\n  mutation CreateDocument($input: CreateDocumentInput!) {\n    createDocument(input: $input) { id revision }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateDocument($id: ID!, $revision: Int!, $input: UpdateDocumentInput!) {\n    updateDocument(id: $id, revision: $revision, input: $input) {\n      id\n      title\n      kind\n      content\n      contentJson\n      revision\n      updatedAt\n      updatedBy { id name }\n    }\n  }\n"): (typeof documents)["\n  mutation UpdateDocument($id: ID!, $revision: Int!, $input: UpdateDocumentInput!) {\n    updateDocument(id: $id, revision: $revision, input: $input) {\n      id\n      title\n      kind\n      content\n      contentJson\n      revision\n      updatedAt\n      updatedBy { id name }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteDocument($id: ID!) {\n    deleteDocument(id: $id) { id deletionId }\n  }\n"): (typeof documents)["\n  mutation DeleteDocument($id: ID!) {\n    deleteDocument(id: $id) { id deletionId }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetDocumentSharing($id: ID!, $input: DocumentSharingInput!) {\n    setDocumentSharing(id: $id, input: $input) {\n      id\n      projectAccess\n      viewerAccess\n      shares { user { id name avatarUrl isAgent } access }\n    }\n  }\n"): (typeof documents)["\n  mutation SetDocumentSharing($id: ID!, $input: DocumentSharingInput!) {\n    setDocumentSharing(id: $id, input: $input) {\n      id\n      projectAccess\n      viewerAccess\n      shares { user { id name avatarUrl isAgent } access }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation TransferDocument($id: ID!, $ownerId: ID!) {\n    transferDocument(id: $id, ownerId: $ownerId) {\n      id\n      viewerAccess\n      owner { id name avatarUrl isAgent }\n    }\n  }\n"): (typeof documents)["\n  mutation TransferDocument($id: ID!, $ownerId: ID!) {\n    transferDocument(id: $id, ownerId: $ownerId) {\n      id\n      viewerAccess\n      owner { id name avatarUrl isAgent }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ExportDocumentPdf($id: ID!) {\n    exportDocumentPdf(id: $id) { fileName mimeType contentBase64 }\n  }\n"): (typeof documents)["\n  mutation ExportDocumentPdf($id: ID!) {\n    exportDocumentPdf(id: $id) { fileName mimeType contentBase64 }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query DocumentVersions($id: ID!) {\n    document(id: $id) {\n      id\n      title\n      revision\n      viewerAccess\n      versions {\n        revision\n        title\n        kind\n        restoredFrom\n        createdAt\n        createdBy { id name }\n      }\n    }\n  }\n"): (typeof documents)["\n  query DocumentVersions($id: ID!) {\n    document(id: $id) {\n      id\n      title\n      revision\n      viewerAccess\n      versions {\n        revision\n        title\n        kind\n        restoredFrom\n        createdAt\n        createdBy { id name }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query DocumentVersion($documentId: ID!, $revision: Int!) {\n    documentVersion(documentId: $documentId, revision: $revision) {\n      revision\n      title\n      content\n    }\n  }\n"): (typeof documents)["\n  query DocumentVersion($documentId: ID!, $revision: Int!) {\n    documentVersion(documentId: $documentId, revision: $revision) {\n      revision\n      title\n      content\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RestoreDocumentVersion($id: ID!, $revision: Int!) {\n    restoreDocumentVersion(id: $id, revision: $revision) { id revision }\n  }\n"): (typeof documents)["\n  mutation RestoreDocumentVersion($id: ID!, $revision: Int!) {\n    restoreDocumentVersion(id: $id, revision: $revision) { id revision }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Ideas($status: IdeaStatus, $first: Int!) {\n    ideas(status: $status, first: $first) {\n      data {\n        id\n        title\n        description\n        status\n        draft\n        createdAt\n        adoptedAt\n        viewerCanUpdate\n        project { id name }\n        author { id name }\n        todos { id title project { id } }\n      }\n    }\n  }\n"): (typeof documents)["\n  query Ideas($status: IdeaStatus, $first: Int!) {\n    ideas(status: $status, first: $first) {\n      data {\n        id\n        title\n        description\n        status\n        draft\n        createdAt\n        adoptedAt\n        viewerCanUpdate\n        project { id name }\n        author { id name }\n        todos { id title project { id } }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateIdea($input: CreateIdeaInput!) {\n    createIdea(input: $input) { id title status }\n  }\n"): (typeof documents)["\n  mutation CreateIdea($input: CreateIdeaInput!) {\n    createIdea(input: $input) { id title status }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateIdea($id: ID!, $input: UpdateIdeaInput!) {\n    updateIdea(id: $id, input: $input) { id title description status draft project { id name } }\n  }\n"): (typeof documents)["\n  mutation UpdateIdea($id: ID!, $input: UpdateIdeaInput!) {\n    updateIdea(id: $id, input: $input) { id title description status draft project { id name } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetIdeaStatus($id: ID!, $status: IdeaStatus!) {\n    setIdeaStatus(id: $id, status: $status) { id status adoptedAt }\n  }\n"): (typeof documents)["\n  mutation SetIdeaStatus($id: ID!, $status: IdeaStatus!) {\n    setIdeaStatus(id: $id, status: $status) { id status adoptedAt }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteIdea($id: ID!) {\n    deleteIdea(id: $id) { id deletionId }\n  }\n"): (typeof documents)["\n  mutation DeleteIdea($id: ID!) {\n    deleteIdea(id: $id) { id deletionId }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Templates {\n    viewerRights\n    templates {\n      id\n      key\n      name\n      description\n      locale\n      isBuiltin\n      projectCount\n      viewerCanUpdate\n      viewerCanDelete\n      createdBy { id name }\n      labels { key name names { locale name } category categoryLabel color }\n      outline { title version todos { title tasks { title labels } } }\n      file\n    }\n  }\n"): (typeof documents)["\n  query Templates {\n    viewerRights\n    templates {\n      id\n      key\n      name\n      description\n      locale\n      isBuiltin\n      projectCount\n      viewerCanUpdate\n      viewerCanDelete\n      createdBy { id name }\n      labels { key name names { locale name } category categoryLabel color }\n      outline { title version todos { title tasks { title labels } } }\n      file\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateTemplate($input: TemplateInput!) {\n    createTemplate(input: $input) { id name }\n  }\n"): (typeof documents)["\n  mutation CreateTemplate($input: TemplateInput!) {\n    createTemplate(input: $input) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DuplicateTemplate($id: ID!) {\n    duplicateTemplate(id: $id) { id name }\n  }\n"): (typeof documents)["\n  mutation DuplicateTemplate($id: ID!) {\n    duplicateTemplate(id: $id) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateTemplate($id: ID!, $input: UpdateTemplateInput!) {\n    updateTemplate(id: $id, input: $input) { id name }\n  }\n"): (typeof documents)["\n  mutation UpdateTemplate($id: ID!, $input: UpdateTemplateInput!) {\n    updateTemplate(id: $id, input: $input) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteTemplate($id: ID!) {\n    deleteTemplate(id: $id)\n  }\n"): (typeof documents)["\n  mutation DeleteTemplate($id: ID!) {\n    deleteTemplate(id: $id)\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectLabels($id: ID!) {\n    project(id: $id) {\n      id\n      locale\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n"): (typeof documents)["\n  query ProjectLabels($id: ID!) {\n    project(id: $id) {\n      id\n      locale\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateProjectLabels($projectId: ID!, $labels: [LabelInput!]!) {\n    updateProjectLabels(projectId: $projectId, labels: $labels) {\n      id\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n"): (typeof documents)["\n  mutation UpdateProjectLabels($projectId: ID!, $labels: [LabelInput!]!) {\n    updateProjectLabels(projectId: $projectId, labels: $labels) {\n      id\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ApplyTemplateLabels($projectId: ID!, $templateId: ID!, $mode: TemplateLabelMode!) {\n    applyTemplateLabels(projectId: $projectId, templateId: $templateId, mode: $mode) {\n      id\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n"): (typeof documents)["\n  mutation ApplyTemplateLabels($projectId: ID!, $templateId: ID!, $mode: TemplateLabelMode!) {\n    applyTemplateLabels(projectId: $projectId, templateId: $templateId, mode: $mode) {\n      id\n      labels { id key name names { locale name } category categoryLabel color position taskCount }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {\n    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {\n      id\n      labels { id name color position }\n    }\n  }\n"): (typeof documents)["\n  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {\n    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {\n      id\n      labels { id name color position }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ExportPlan($projectId: ID!) {\n    exportPlan(projectId: $projectId)\n  }\n"): (typeof documents)["\n  query ExportPlan($projectId: ID!) {\n    exportPlan(projectId: $projectId)\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ImportPlan($projectId: ID, $document: String!, $mode: ImportMode, $dryRun: Boolean, $notifyAssignees: Boolean, $templateId: ID, $ideaId: ID, $appendOnly: Boolean) {\n    importPlan(projectId: $projectId, document: $document, mode: $mode, dryRun: $dryRun, notifyAssignees: $notifyAssignees, templateId: $templateId, ideaId: $ideaId, appendOnly: $appendOnly) {\n      ok\n      dryRun\n      projectId\n      changes { kind path id fields title }\n      errors { path kind message }\n    }\n  }\n"): (typeof documents)["\n  mutation ImportPlan($projectId: ID, $document: String!, $mode: ImportMode, $dryRun: Boolean, $notifyAssignees: Boolean, $templateId: ID, $ideaId: ID, $appendOnly: Boolean) {\n    importPlan(projectId: $projectId, document: $document, mode: $mode, dryRun: $dryRun, notifyAssignees: $notifyAssignees, templateId: $templateId, ideaId: $ideaId, appendOnly: $appendOnly) {\n      ok\n      dryRun\n      projectId\n      changes { kind path id fields title }\n      errors { path kind message }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateTemplateFromProject($projectId: ID!, $name: String!) {\n    createTemplateFromProject(projectId: $projectId, name: $name) { id name }\n  }\n"): (typeof documents)["\n  mutation CreateTemplateFromProject($projectId: ID!, $name: String!) {\n    createTemplateFromProject(projectId: $projectId, name: $name) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateTemplateFromFile($document: String!) {\n    createTemplateFromFile(document: $document) { id name }\n  }\n"): (typeof documents)["\n  mutation CreateTemplateFromFile($document: String!) {\n    createTemplateFromFile(document: $document) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectWebhook($id: ID!) {\n    project(id: $id) {\n      id\n      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }\n    }\n  }\n"): (typeof documents)["\n  query ProjectWebhook($id: ID!) {\n    project(id: $id) {\n      id\n      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetProjectWebhook($projectId: ID!, $input: ProjectWebhookInput!) {\n    setProjectWebhook(projectId: $projectId, input: $input) {\n      id\n      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }\n    }\n  }\n"): (typeof documents)["\n  mutation SetProjectWebhook($projectId: ID!, $input: ProjectWebhookInput!) {\n    setProjectWebhook(projectId: $projectId, input: $input) {\n      id\n      webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RemoveProjectWebhook($projectId: ID!) {\n    removeProjectWebhook(projectId: $projectId) { id webhook { kind } }\n  }\n"): (typeof documents)["\n  mutation RemoveProjectWebhook($projectId: ID!) {\n    removeProjectWebhook(projectId: $projectId) { id webhook { kind } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation TestProjectWebhook($projectId: ID!) {\n    testProjectWebhook(projectId: $projectId) { ok message }\n  }\n"): (typeof documents)["\n  mutation TestProjectWebhook($projectId: ID!) {\n    testProjectWebhook(projectId: $projectId) { ok message }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query MyTasks($status: TaskStatus) {\n    myTasks(status: $status) {\n      id\n      title\n      icon\n      status\n      dueDate\n      userStory { role goal benefit }\n      commentCount\n      description\n      assignee { id name }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n    }\n  }\n"): (typeof documents)["\n  query MyTasks($status: TaskStatus) {\n    myTasks(status: $status) {\n      id\n      title\n      icon\n      status\n      dueDate\n      userStory { role goal benefit }\n      commentCount\n      description\n      assignee { id name }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateMyNotifications($notifyByEmail: Boolean!) {\n    updateMyNotifications(notifyByEmail: $notifyByEmail) { id notifyByEmail }\n  }\n"): (typeof documents)["\n  mutation UpdateMyNotifications($notifyByEmail: Boolean!) {\n    updateMyNotifications(notifyByEmail: $notifyByEmail) { id notifyByEmail }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateMyAvatar($avatar: Upload!) {\n    updateMyAvatar(avatar: $avatar) { id avatarUrl }\n  }\n"): (typeof documents)["\n  mutation UpdateMyAvatar($avatar: Upload!) {\n    updateMyAvatar(avatar: $avatar) { id avatarUrl }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteUserAvatar($id: ID!) {\n    deleteUserAvatar(id: $id) { id avatarUrl }\n  }\n"): (typeof documents)["\n  mutation DeleteUserAvatar($id: ID!) {\n    deleteUserAvatar(id: $id) { id avatarUrl }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status date }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status date }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectTimeline($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status date }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n          tasks {\n            id\n            title\n            description\n            icon\n            status\n            dueDate\n            userStory { role goal benefit }\n            commentCount\n            labels { id name color position }\n            assignee { id name avatarUrl }\n            viewerCanUpdate\n            viewerCanDelete\n          }\n        }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectTimeline($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status date }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n          tasks {\n            id\n            title\n            description\n            icon\n            status\n            dueDate\n            userStory { role goal benefit }\n            commentCount\n            labels { id name color position }\n            assignee { id name avatarUrl }\n            viewerCanUpdate\n            viewerCanDelete\n          }\n        }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      description\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n"): (typeof documents)["\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      description\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n"): (typeof documents)["\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status date } }\n  }\n"): (typeof documents)["\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status date } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation MoveMilestone($id: ID!, $position: Int!) {\n    moveMilestone(id: $id, position: $position) { id }\n  }\n"): (typeof documents)["\n  mutation MoveMilestone($id: ID!, $position: Int!) {\n    moveMilestone(id: $id, position: $position) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id deletionId }\n  }\n"): (typeof documents)["\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id deletionId }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateTodo($input: CreateTodoInput!) {\n    createTodo(input: $input) { id }\n  }\n"): (typeof documents)["\n  mutation CreateTodo($input: CreateTodoInput!) {\n    createTodo(input: $input) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title description icon dueDate }\n  }\n"): (typeof documents)["\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title description icon dueDate }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id deletionId }\n  }\n"): (typeof documents)["\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id deletionId }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Projects($archived: Boolean = false) {\n    projects(archived: $archived) {\n      id\n      name\n      description\n      viewerRole { key names { de en } }\n      progress { done total percent }\n      counts { milestones todos tasks }\n      members {\n        role { key names { de en } }\n        user { id name avatarUrl }\n      }\n    }\n  }\n"): (typeof documents)["\n  query Projects($archived: Boolean = false) {\n    projects(archived: $archived) {\n      id\n      name\n      description\n      viewerRole { key names { de en } }\n      progress { done total percent }\n      counts { milestones todos tasks }\n      members {\n        role { key names { de en } }\n        user { id name avatarUrl }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n      name\n      description\n      icon\n      archivedAt\n      viewerRole { key names { de en } }\n      viewerRights\n      labels { id name category color position }\n    }\n  }\n"): (typeof documents)["\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n      name\n      description\n      icon\n      archivedAt\n      viewerRole { key names { de en } }\n      viewerRights\n      labels { id name category color position }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectRevision($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n    }\n  }\n"): (typeof documents)["\n  query ProjectRevision($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateProject($input: CreateProjectInput!) {\n    createProject(input: $input) {\n      id\n      name\n    }\n  }\n"): (typeof documents)["\n  mutation CreateProject($input: CreateProjectInput!) {\n    createProject(input: $input) {\n      id\n      name\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query SidebarProjects {\n    projects { id name icon }\n  }\n"): (typeof documents)["\n  query SidebarProjects {\n    projects { id name icon }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query PermissionMatrix {\n    permissionMatrix {\n      system { right roles { key } }\n      project { right roles { key } }\n    }\n  }\n"): (typeof documents)["\n  query PermissionMatrix {\n    permissionMatrix {\n      system { right roles { key } }\n      project { right roles { key } }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Roles($scope: RoleScope) {\n    roles(scope: $scope) {\n      id\n      key\n      scope\n      isDefault\n      names { de en }\n      rights\n    }\n  }\n"): (typeof documents)["\n  query Roles($scope: RoleScope) {\n    roles(scope: $scope) {\n      id\n      key\n      scope\n      isDefault\n      names { de en }\n      rights\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query RoleSettings {\n    roles {\n      id\n      key\n      scope\n      isDefault\n      isStandard\n      names { de en }\n      functions { id key names { de en } }\n      projectCap\n      usageCount\n    }\n    roleFunctions {\n      id\n      key\n      scope\n      isStandard\n      names { de en }\n      rights\n      usageCount\n    }\n    systemRights: rights(scope: SYSTEM)\n    projectRights: rights(scope: PROJECT)\n  }\n"): (typeof documents)["\n  query RoleSettings {\n    roles {\n      id\n      key\n      scope\n      isDefault\n      isStandard\n      names { de en }\n      functions { id key names { de en } }\n      projectCap\n      usageCount\n    }\n    roleFunctions {\n      id\n      key\n      scope\n      isStandard\n      names { de en }\n      rights\n      usageCount\n    }\n    systemRights: rights(scope: SYSTEM)\n    projectRights: rights(scope: PROJECT)\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateRole($input: CreateRoleInput!) {\n    createRole(input: $input) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation CreateRole($input: CreateRoleInput!) {\n    createRole(input: $input) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateRole($id: ID!, $input: UpdateRoleInput!) {\n    updateRole(id: $id, input: $input) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation UpdateRole($id: ID!, $input: UpdateRoleInput!) {\n    updateRole(id: $id, input: $input) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DuplicateRole($id: ID!) {\n    duplicateRole(id: $id) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation DuplicateRole($id: ID!) {\n    duplicateRole(id: $id) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteRole($id: ID!) {\n    deleteRole(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteRole($id: ID!) {\n    deleteRole(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetDefaultRole($id: ID!) {\n    setDefaultRole(id: $id) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation SetDefaultRole($id: ID!) {\n    setDefaultRole(id: $id) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ResetRole($id: ID!) {\n    resetRole(id: $id) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation ResetRole($id: ID!) {\n    resetRole(id: $id) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateRoleFunction($input: CreateRoleFunctionInput!) {\n    createRoleFunction(input: $input) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation CreateRoleFunction($input: CreateRoleFunctionInput!) {\n    createRoleFunction(input: $input) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateRoleFunction($id: ID!, $input: UpdateRoleFunctionInput!) {\n    updateRoleFunction(id: $id, input: $input) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation UpdateRoleFunction($id: ID!, $input: UpdateRoleFunctionInput!) {\n    updateRoleFunction(id: $id, input: $input) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DuplicateRoleFunction($id: ID!) {\n    duplicateRoleFunction(id: $id) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation DuplicateRoleFunction($id: ID!) {\n    duplicateRoleFunction(id: $id) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteRoleFunction($id: ID!) {\n    deleteRoleFunction(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteRoleFunction($id: ID!) {\n    deleteRoleFunction(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ResetRoleFunction($id: ID!) {\n    resetRoleFunction(id: $id) { id key names { de en } }\n  }\n"): (typeof documents)["\n  mutation ResetRoleFunction($id: ID!) {\n    resetRoleFunction(id: $id) { id key names { de en } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Security {\n    me {\n      id\n      twoFactorConfirmed\n      hasPasskey\n      passkeys {\n        id\n        name\n        createdAt\n        lastUsedAt\n      }\n    }\n  }\n"): (typeof documents)["\n  query Security {\n    me {\n      id\n      twoFactorConfirmed\n      hasPasskey\n      passkeys {\n        id\n        name\n        createdAt\n        lastUsedAt\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ApiTokens {\n    me {\n      id\n      apiTokens { id name createdAt lastUsedAt expiresAt }\n    }\n  }\n"): (typeof documents)["\n  query ApiTokens {\n    me {\n      id\n      apiTokens { id name createdAt lastUsedAt expiresAt }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateApiToken($name: String!) {\n    createApiToken(name: $name) {\n      plainTextToken\n      token { id name }\n    }\n  }\n"): (typeof documents)["\n  mutation CreateApiToken($name: String!) {\n    createApiToken(name: $name) {\n      plainTextToken\n      token { id name }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteApiToken($id: ID!) {\n    deleteApiToken(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteApiToken($id: ID!) {\n    deleteApiToken(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectSettings($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      icon\n      locale\n      members {\n        role { key names { de en } }\n        user { id name email avatarUrl isAgent }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectSettings($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      icon\n      locale\n      members {\n        role { key names { de en } }\n        user { id name email avatarUrl isAgent }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query MemberCandidates($projectId: ID) {\n    memberCandidates(projectId: $projectId) { id name avatarUrl isMember isAgent }\n  }\n"): (typeof documents)["\n  query MemberCandidates($projectId: ID) {\n    memberCandidates(projectId: $projectId) { id name avatarUrl isMember isAgent }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {\n    updateProject(id: $id, input: $input) { id name description icon locale }\n  }\n"): (typeof documents)["\n  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {\n    updateProject(id: $id, input: $input) { id name description icon locale }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {\n    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl isAgent } }\n    }\n  }\n"): (typeof documents)["\n  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {\n    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl isAgent } }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {\n    removeProjectMember(projectId: $projectId, userId: $userId) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl isAgent } }\n    }\n  }\n"): (typeof documents)["\n  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {\n    removeProjectMember(projectId: $projectId, userId: $userId) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl isAgent } }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteProject($id: ID!) {\n    deleteProject(id: $id) { id deletionId }\n  }\n"): (typeof documents)["\n  mutation DeleteProject($id: ID!) {\n    deleteProject(id: $id) { id deletionId }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ArchiveProject($id: ID!) {\n    archiveProject(id: $id) { id archivedAt viewerRights }\n  }\n"): (typeof documents)["\n  mutation ArchiveProject($id: ID!) {\n    archiveProject(id: $id) { id archivedAt viewerRights }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UnarchiveProject($id: ID!) {\n    unarchiveProject(id: $id) { id archivedAt viewerRights }\n  }\n"): (typeof documents)["\n  mutation UnarchiveProject($id: ID!) {\n    unarchiveProject(id: $id) { id archivedAt viewerRights }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectStatistics($projectId: ID!) {\n    projectStatistics(projectId: $projectId) {\n      milestones { total released planned unversioned }\n      todos\n      tasks\n      progress { total done percent }\n      tasksByStatus { status count percent }\n      tasksByLabel { label { id name color position } count }\n      tasksByPerson { user { id name avatarUrl isAgent } open done }\n      due { overdue soon undated }\n      perMilestone {\n        milestone { id title status release { version status date } }\n        todos\n        tasks\n        progress { total done percent }\n      }\n      doneByWeek { weekStart count }\n      withCommit\n      comments\n    }\n  }\n"): (typeof documents)["\n  query ProjectStatistics($projectId: ID!) {\n    projectStatistics(projectId: $projectId) {\n      milestones { total released planned unversioned }\n      todos\n      tasks\n      progress { total done percent }\n      tasksByStatus { status count percent }\n      tasksByLabel { label { id name color position } count }\n      tasksByPerson { user { id name avatarUrl isAgent } open done }\n      due { overdue soon undated }\n      perMilestone {\n        milestone { id title status release { version status date } }\n        todos\n        tasks\n        progress { total done percent }\n      }\n      doneByWeek { weekStart count }\n      withCommit\n      comments\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Kanban($projectId: ID!) {\n    kanban(projectId: $projectId) {\n      status\n      tasks {\n        id\n        title\n        icon\n        status\n        boardPosition\n        dueDate\n        commit\n        userStory { role goal benefit }\n        commentCount\n        labels { id name color position }\n        assignee { id name avatarUrl }\n        todo { id title }\n        viewerCanUpdate\n        viewerCanDelete\n      }\n    }\n  }\n"): (typeof documents)["\n  query Kanban($projectId: ID!) {\n    kanban(projectId: $projectId) {\n      status\n      tasks {\n        id\n        title\n        icon\n        status\n        boardPosition\n        dueDate\n        commit\n        userStory { role goal benefit }\n        commentCount\n        labels { id name color position }\n        assignee { id name avatarUrl }\n        todo { id title }\n        viewerCanUpdate\n        viewerCanDelete\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {\n    moveTaskToTodo(id: $id, todoId: $todoId) { id }\n  }\n"): (typeof documents)["\n  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {\n    moveTaskToTodo(id: $id, todoId: $todoId) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation MoveTaskInTodo($id: ID!, $position: Int!) {\n    moveTaskInTodo(id: $id, position: $position) { id }\n  }\n"): (typeof documents)["\n  mutation MoveTaskInTodo($id: ID!, $position: Int!) {\n    moveTaskInTodo(id: $id, position: $position) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectMembers($id: ID!) {\n    project(id: $id) {\n      id\n      members {\n        role { key }\n        user { id name avatarUrl isAgent }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectMembers($id: ID!) {\n    project(id: $id) {\n      id\n      members {\n        role { key }\n        user { id name avatarUrl isAgent }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query TaskDetail($id: ID!) {\n    task(id: $id) {\n      id\n      title\n      description\n      icon\n      status\n      boardPosition\n      dueDate\n      commit\n      userStory { role goal benefit }\n      assignee { id name avatarUrl }\n      labels { id name color position }\n      viewerCanUpdate\n      viewerCanAssign\n      viewerCanAssignSelf\n      viewerCanDelete\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name labels { id name category color position } }\n        }\n      }\n      comments {\n        id\n        body\n        createdAt\n        editedAt\n        viewerCanEdit\n        viewerCanDelete\n        author { id name avatarUrl isAgent }\n      }\n    }\n  }\n"): (typeof documents)["\n  query TaskDetail($id: ID!) {\n    task(id: $id) {\n      id\n      title\n      description\n      icon\n      status\n      boardPosition\n      dueDate\n      commit\n      userStory { role goal benefit }\n      assignee { id name avatarUrl }\n      labels { id name color position }\n      viewerCanUpdate\n      viewerCanAssign\n      viewerCanAssignSelf\n      viewerCanDelete\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name labels { id name category color position } }\n        }\n      }\n      comments {\n        id\n        body\n        createdAt\n        editedAt\n        viewerCanEdit\n        viewerCanDelete\n        author { id name avatarUrl isAgent }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateTask($input: CreateTaskInput!) {\n    createTask(input: $input) { id }\n  }\n"): (typeof documents)["\n  mutation CreateTask($input: CreateTaskInput!) {\n    createTask(input: $input) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateTask($id: ID!, $input: UpdateTaskInput!) {\n    updateTask(id: $id, input: $input) { id title description icon dueDate userStory { role goal benefit } }\n  }\n"): (typeof documents)["\n  mutation UpdateTask($id: ID!, $input: UpdateTaskInput!) {\n    updateTask(id: $id, input: $input) { id title description icon dueDate userStory { role goal benefit } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation MoveTask($id: ID!, $status: TaskStatus!, $position: Int!) {\n    moveTask(id: $id, status: $status, position: $position) { id status boardPosition }\n  }\n"): (typeof documents)["\n  mutation MoveTask($id: ID!, $status: TaskStatus!, $position: Int!) {\n    moveTask(id: $id, status: $status, position: $position) { id status boardPosition }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation AssignTask($id: ID!, $assigneeId: ID) {\n    assignTask(id: $id, assigneeId: $assigneeId) { id assignee { id name avatarUrl } }\n  }\n"): (typeof documents)["\n  mutation AssignTask($id: ID!, $assigneeId: ID) {\n    assignTask(id: $id, assigneeId: $assigneeId) { id assignee { id name avatarUrl } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteTask($id: ID!) {\n    deleteTask(id: $id) { id deletionId }\n  }\n"): (typeof documents)["\n  mutation DeleteTask($id: ID!) {\n    deleteTask(id: $id) { id deletionId }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation AddComment($input: AddCommentInput!) {\n    addComment(input: $input) { id }\n  }\n"): (typeof documents)["\n  mutation AddComment($input: AddCommentInput!) {\n    addComment(input: $input) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {\n    updateComment(id: $id, input: $input) { id body editedAt }\n  }\n"): (typeof documents)["\n  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {\n    updateComment(id: $id, input: $input) { id body editedAt }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteComment($id: ID!) {\n    deleteComment(id: $id) { id deletionId }\n  }\n"): (typeof documents)["\n  mutation DeleteComment($id: ID!) {\n    deleteComment(id: $id) { id deletionId }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Trash($projectId: ID) {\n    trash(projectId: $projectId) {\n      deletionId\n      type\n      id\n      title\n      deletedAt\n      deletedBy { id name avatarUrl }\n      project { id name icon }\n      childCount\n      childCounts { type count }\n      viewerCanRestore\n      viewerCanPurge\n    }\n  }\n"): (typeof documents)["\n  query Trash($projectId: ID) {\n    trash(projectId: $projectId) {\n      deletionId\n      type\n      id\n      title\n      deletedAt\n      deletedBy { id name avatarUrl }\n      project { id name icon }\n      childCount\n      childCounts { type count }\n      viewerCanRestore\n      viewerCanPurge\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RestoreFromTrash($deletionId: ID!) {\n    restoreFromTrash(deletionId: $deletionId) { deletionId type id title }\n  }\n"): (typeof documents)["\n  mutation RestoreFromTrash($deletionId: ID!) {\n    restoreFromTrash(deletionId: $deletionId) { deletionId type id title }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation PurgeFromTrash($deletionId: ID!) {\n    purgeFromTrash(deletionId: $deletionId)\n  }\n"): (typeof documents)["\n  mutation PurgeFromTrash($deletionId: ID!) {\n    purgeFromTrash(deletionId: $deletionId)\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation EmptyProjectTrash($projectId: ID!) {\n    emptyProjectTrash(projectId: $projectId)\n  }\n"): (typeof documents)["\n  mutation EmptyProjectTrash($projectId: ID!) {\n    emptyProjectTrash(projectId: $projectId)\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      role { key names { de en } }\n      activity\n      avatarUrl\n      notifyByEmail\n      approved\n      emailVerified\n    }\n    appSettings {\n      twoFactorEnabled\n      isTestEnvironment\n    }\n    viewerRights\n  }\n"): (typeof documents)["\n  query Viewer {\n    me {\n      id\n      name\n      email\n      role { key names { de en } }\n      activity\n      avatarUrl\n      notifyByEmail\n      approved\n      emailVerified\n    }\n    appSettings {\n      twoFactorEnabled\n      isTestEnvironment\n    }\n    viewerRights\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ZenProject($id: ID!) {\n    project(id: $id) { id name }\n  }\n"): (typeof documents)["\n  query ZenProject($id: ID!) {\n    project(id: $id) { id name }\n  }\n"];

export function graphql(source: string) {
  return (documents as any)[source] ?? {};
}

export type DocumentType<TDocumentNode extends DocumentNode<any, any>> = TDocumentNode extends DocumentNode<  infer TType,  any>  ? TType  : never;