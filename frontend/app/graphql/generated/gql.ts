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
    "\n  query AdminUsers {\n    users {\n      id\n      name\n      email\n      role { key isDefault names { de en } }\n      activity\n      avatarUrl\n      twoFactorConfirmed\n      hasPasskey\n      createdAt\n      approved\n      emailVerified\n    }\n  }\n": typeof types.AdminUsersDocument,
    "\n  mutation ApproveUser($id: ID!) {\n    approveUser(id: $id) { id approved }\n  }\n": typeof types.ApproveUserDocument,
    "\n  mutation RejectUser($id: ID!) {\n    rejectUser(id: $id)\n  }\n": typeof types.RejectUserDocument,
    "\n  mutation CreateUser($input: CreateUserInput!) {\n    createUser(input: $input) { id name }\n  }\n": typeof types.CreateUserDocument,
    "\n  mutation UpdateUser($id: ID!, $input: UpdateUserInput!) {\n    updateUser(id: $id, input: $input) { id name email role { key isDefault names { de en } } activity }\n  }\n": typeof types.UpdateUserDocument,
    "\n  mutation ResetUserTwoFactor($id: ID!) {\n    resetUserTwoFactor(id: $id) { id twoFactorConfirmed hasPasskey }\n  }\n": typeof types.ResetUserTwoFactorDocument,
    "\n  mutation DeleteUser($id: ID!) {\n    deleteUser(id: $id) { id }\n  }\n": typeof types.DeleteUserDocument,
    "\n  query AppSettings {\n    appSettings { twoFactorEnabled twoFactorSwitchable projectCreatorRole { key names { de en } } }\n  }\n": typeof types.AppSettingsDocument,
    "\n  mutation SetProjectCreatorRole($key: String!) {\n    setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key names { de en } } }\n  }\n": typeof types.SetProjectCreatorRoleDocument,
    "\n  mutation UpdateAppSettings($twoFactorEnabled: Boolean!) {\n    updateAppSettings(twoFactorEnabled: $twoFactorEnabled) { twoFactorEnabled twoFactorSwitchable }\n  }\n": typeof types.UpdateAppSettingsDocument,
    "\n  mutation DraftProjectPlan($messages: [PlanChatMessageInput!]!, $currentPlan: ProjectPlanInput) {\n    draftProjectPlan(messages: $messages, currentPlan: $currentPlan) {\n      reply\n      plan {\n        name\n        description\n        locale\n        members { email role }\n        milestones {\n          title\n          description\n          dueDate\n          todos {\n            title\n            description\n            dueDate\n            tasks { title description dueDate assigneeEmail }\n          }\n        }\n      }\n    }\n  }\n": typeof types.DraftProjectPlanDocument,
    "\n  mutation ImportProjectPlan($plan: ProjectPlanInput!, $notifyAssignees: Boolean) {\n    importProjectPlan(plan: $plan, notifyAssignees: $notifyAssignees) {\n      id\n      name\n    }\n  }\n": typeof types.ImportProjectPlanDocument,
    "\n  query AssistantPeople {\n    users { id name email avatarUrl }\n  }\n": typeof types.AssistantPeopleDocument,
    "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole { key names { de en } }\n        progress { done total percent }\n      }\n    }\n  }\n": typeof types.DashboardDocument,
    "\n  query LabelSets {\n    viewerRights\n    labelSets {\n      id\n      name\n      description\n      isBuiltin\n      usedByProjects\n      viewerCanUpdate\n      viewerCanDelete\n      labels { id name category color position }\n    }\n  }\n": typeof types.LabelSetsDocument,
    "\n  mutation SetProjectLabelSet($projectId: ID!, $labelSetId: ID) {\n    setProjectLabelSet(projectId: $projectId, labelSetId: $labelSetId) {\n      id\n      labelSet { id name labels { id name category color position } }\n    }\n  }\n": typeof types.SetProjectLabelSetDocument,
    "\n  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {\n    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {\n      id\n      labels { id name color position }\n    }\n  }\n": typeof types.SetTaskLabelsDocument,
    "\n  mutation CreateLabelSet($input: LabelSetInput!) {\n    createLabelSet(input: $input) { id name }\n  }\n": typeof types.CreateLabelSetDocument,
    "\n  mutation DuplicateLabelSet($id: ID!) {\n    duplicateLabelSet(id: $id) { id name }\n  }\n": typeof types.DuplicateLabelSetDocument,
    "\n  mutation UpdateLabelSet($id: ID!, $input: LabelSetInput!) {\n    updateLabelSet(id: $id, input: $input) { id name }\n  }\n": typeof types.UpdateLabelSetDocument,
    "\n  mutation DeleteLabelSet($id: ID!) {\n    deleteLabelSet(id: $id) { id }\n  }\n": typeof types.DeleteLabelSetDocument,
    "\n  query MyTasks($status: TaskStatus) {\n    myTasks(status: $status) {\n      id\n      title\n      icon\n      status\n      dueDate\n      userStory { role goal benefit }\n      commentCount\n      description\n      assignee { id name }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n    }\n  }\n": typeof types.MyTasksDocument,
    "\n  mutation UpdateMyNotifications($emailEnabled: Boolean!) {\n    updateMyNotifications(emailEnabled: $emailEnabled) { id notifyByEmail }\n  }\n": typeof types.UpdateMyNotificationsDocument,
    "\n  mutation UpdateMyAvatar($avatar: Upload!) {\n    updateMyAvatar(avatar: $avatar) { id avatarUrl }\n  }\n": typeof types.UpdateMyAvatarDocument,
    "\n  mutation DeleteUserAvatar($id: ID!) {\n    deleteUserAvatar(id: $id) { id avatarUrl }\n  }\n": typeof types.DeleteUserAvatarDocument,
    "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n": typeof types.ProjectOverviewDocument,
    "\n  query ProjectTimeline($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n          tasks {\n            id\n            title\n            description\n            icon\n            status\n            dueDate\n            userStory { role goal benefit }\n            commentCount\n            labels { id name color position }\n            assignee { id name avatarUrl }\n          }\n        }\n      }\n    }\n  }\n": typeof types.ProjectTimelineDocument,
    "\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      description\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n": typeof types.ProjectTodosDocument,
    "\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n": typeof types.CreateMilestoneDocument,
    "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status } }\n  }\n": typeof types.UpdateMilestoneDocument,
    "\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id }\n  }\n": typeof types.DeleteMilestoneDocument,
    "\n  mutation CreateTodo($input: CreateTodoInput!) {\n    createTodo(input: $input) { id }\n  }\n": typeof types.CreateTodoDocument,
    "\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title description icon dueDate }\n  }\n": typeof types.UpdateTodoDocument,
    "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id }\n  }\n": typeof types.DeleteTodoDocument,
    "\n  query Projects {\n    projects {\n      id\n      name\n      description\n      viewerRole { key names { de en } }\n      progress { done total percent }\n      members {\n        role { key names { de en } }\n        user { id name avatarUrl }\n      }\n    }\n  }\n": typeof types.ProjectsDocument,
    "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n      name\n      description\n      icon\n      viewerRole { key names { de en } }\n      viewerRights\n      labelSet { id name labels { id name category color position } }\n    }\n  }\n": typeof types.ProjectHeaderDocument,
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
    "\n  query ProjectSettings($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      icon\n      locale\n      members {\n        role { key names { de en } }\n        user { id name email avatarUrl }\n      }\n    }\n  }\n": typeof types.ProjectSettingsDocument,
    "\n  query MemberCandidates($projectId: ID) {\n    memberCandidates(projectId: $projectId) { id name avatarUrl isMember }\n  }\n": typeof types.MemberCandidatesDocument,
    "\n  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {\n    updateProject(id: $id, input: $input) { id name description icon locale }\n  }\n": typeof types.UpdateProjectDocument,
    "\n  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {\n    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl } }\n    }\n  }\n": typeof types.SetProjectMemberDocument,
    "\n  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {\n    removeProjectMember(projectId: $projectId, userId: $userId) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl } }\n    }\n  }\n": typeof types.RemoveProjectMemberDocument,
    "\n  mutation DeleteProject($id: ID!) {\n    deleteProject(id: $id) { id }\n  }\n": typeof types.DeleteProjectDocument,
    "\n  query Kanban($projectId: ID!) {\n    kanban(projectId: $projectId) {\n      status\n      tasks {\n        id\n        title\n        icon\n        status\n        boardPosition\n        dueDate\n        commit\n        userStory { role goal benefit }\n        commentCount\n        labels { id name color position }\n        assignee { id name avatarUrl }\n        todo { id title }\n      }\n    }\n  }\n": typeof types.KanbanDocument,
    "\n  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {\n    moveTaskToTodo(id: $id, todoId: $todoId) { id }\n  }\n": typeof types.MoveTaskToTodoDocument,
    "\n  query ProjectMembers($id: ID!) {\n    project(id: $id) {\n      id\n      members {\n        role { key }\n        user { id name avatarUrl }\n      }\n    }\n  }\n": typeof types.ProjectMembersDocument,
    "\n  query TaskDetail($id: ID!) {\n    task(id: $id) {\n      id\n      title\n      description\n      icon\n      status\n      boardPosition\n      dueDate\n      commit\n      userStory { role goal benefit }\n      assignee { id name avatarUrl }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name labelSet { id labels { id name category color position } } }\n        }\n      }\n      comments {\n        id\n        body\n        createdAt\n        updatedAt\n        viewerCanEdit\n        viewerCanDelete\n        author { id name avatarUrl }\n      }\n    }\n  }\n": typeof types.TaskDetailDocument,
    "\n  mutation CreateTask($input: CreateTaskInput!) {\n    createTask(input: $input) { id }\n  }\n": typeof types.CreateTaskDocument,
    "\n  mutation UpdateTask($id: ID!, $input: UpdateTaskInput!) {\n    updateTask(id: $id, input: $input) { id title description icon dueDate userStory { role goal benefit } }\n  }\n": typeof types.UpdateTaskDocument,
    "\n  mutation MoveTask($id: ID!, $status: TaskStatus!, $position: Int!) {\n    moveTask(id: $id, status: $status, position: $position) { id status boardPosition }\n  }\n": typeof types.MoveTaskDocument,
    "\n  mutation AssignTask($id: ID!, $assigneeId: ID) {\n    assignTask(id: $id, assigneeId: $assigneeId) { id assignee { id name avatarUrl } }\n  }\n": typeof types.AssignTaskDocument,
    "\n  mutation DeleteTask($id: ID!) {\n    deleteTask(id: $id) { id }\n  }\n": typeof types.DeleteTaskDocument,
    "\n  mutation AddComment($input: AddCommentInput!) {\n    addComment(input: $input) { id }\n  }\n": typeof types.AddCommentDocument,
    "\n  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {\n    updateComment(id: $id, input: $input) { id body updatedAt }\n  }\n": typeof types.UpdateCommentDocument,
    "\n  mutation DeleteComment($id: ID!) {\n    deleteComment(id: $id) { id }\n  }\n": typeof types.DeleteCommentDocument,
    "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      role { key names { de en } }\n      activity\n      avatarUrl\n      notifyByEmail\n      approved\n      emailVerified\n    }\n    appSettings {\n      twoFactorEnabled\n    }\n    viewerRights\n  }\n": typeof types.ViewerDocument,
};
const documents: Documents = {
    "\n  query AdminUsers {\n    users {\n      id\n      name\n      email\n      role { key isDefault names { de en } }\n      activity\n      avatarUrl\n      twoFactorConfirmed\n      hasPasskey\n      createdAt\n      approved\n      emailVerified\n    }\n  }\n": types.AdminUsersDocument,
    "\n  mutation ApproveUser($id: ID!) {\n    approveUser(id: $id) { id approved }\n  }\n": types.ApproveUserDocument,
    "\n  mutation RejectUser($id: ID!) {\n    rejectUser(id: $id)\n  }\n": types.RejectUserDocument,
    "\n  mutation CreateUser($input: CreateUserInput!) {\n    createUser(input: $input) { id name }\n  }\n": types.CreateUserDocument,
    "\n  mutation UpdateUser($id: ID!, $input: UpdateUserInput!) {\n    updateUser(id: $id, input: $input) { id name email role { key isDefault names { de en } } activity }\n  }\n": types.UpdateUserDocument,
    "\n  mutation ResetUserTwoFactor($id: ID!) {\n    resetUserTwoFactor(id: $id) { id twoFactorConfirmed hasPasskey }\n  }\n": types.ResetUserTwoFactorDocument,
    "\n  mutation DeleteUser($id: ID!) {\n    deleteUser(id: $id) { id }\n  }\n": types.DeleteUserDocument,
    "\n  query AppSettings {\n    appSettings { twoFactorEnabled twoFactorSwitchable projectCreatorRole { key names { de en } } }\n  }\n": types.AppSettingsDocument,
    "\n  mutation SetProjectCreatorRole($key: String!) {\n    setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key names { de en } } }\n  }\n": types.SetProjectCreatorRoleDocument,
    "\n  mutation UpdateAppSettings($twoFactorEnabled: Boolean!) {\n    updateAppSettings(twoFactorEnabled: $twoFactorEnabled) { twoFactorEnabled twoFactorSwitchable }\n  }\n": types.UpdateAppSettingsDocument,
    "\n  mutation DraftProjectPlan($messages: [PlanChatMessageInput!]!, $currentPlan: ProjectPlanInput) {\n    draftProjectPlan(messages: $messages, currentPlan: $currentPlan) {\n      reply\n      plan {\n        name\n        description\n        locale\n        members { email role }\n        milestones {\n          title\n          description\n          dueDate\n          todos {\n            title\n            description\n            dueDate\n            tasks { title description dueDate assigneeEmail }\n          }\n        }\n      }\n    }\n  }\n": types.DraftProjectPlanDocument,
    "\n  mutation ImportProjectPlan($plan: ProjectPlanInput!, $notifyAssignees: Boolean) {\n    importProjectPlan(plan: $plan, notifyAssignees: $notifyAssignees) {\n      id\n      name\n    }\n  }\n": types.ImportProjectPlanDocument,
    "\n  query AssistantPeople {\n    users { id name email avatarUrl }\n  }\n": types.AssistantPeopleDocument,
    "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole { key names { de en } }\n        progress { done total percent }\n      }\n    }\n  }\n": types.DashboardDocument,
    "\n  query LabelSets {\n    viewerRights\n    labelSets {\n      id\n      name\n      description\n      isBuiltin\n      usedByProjects\n      viewerCanUpdate\n      viewerCanDelete\n      labels { id name category color position }\n    }\n  }\n": types.LabelSetsDocument,
    "\n  mutation SetProjectLabelSet($projectId: ID!, $labelSetId: ID) {\n    setProjectLabelSet(projectId: $projectId, labelSetId: $labelSetId) {\n      id\n      labelSet { id name labels { id name category color position } }\n    }\n  }\n": types.SetProjectLabelSetDocument,
    "\n  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {\n    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {\n      id\n      labels { id name color position }\n    }\n  }\n": types.SetTaskLabelsDocument,
    "\n  mutation CreateLabelSet($input: LabelSetInput!) {\n    createLabelSet(input: $input) { id name }\n  }\n": types.CreateLabelSetDocument,
    "\n  mutation DuplicateLabelSet($id: ID!) {\n    duplicateLabelSet(id: $id) { id name }\n  }\n": types.DuplicateLabelSetDocument,
    "\n  mutation UpdateLabelSet($id: ID!, $input: LabelSetInput!) {\n    updateLabelSet(id: $id, input: $input) { id name }\n  }\n": types.UpdateLabelSetDocument,
    "\n  mutation DeleteLabelSet($id: ID!) {\n    deleteLabelSet(id: $id) { id }\n  }\n": types.DeleteLabelSetDocument,
    "\n  query MyTasks($status: TaskStatus) {\n    myTasks(status: $status) {\n      id\n      title\n      icon\n      status\n      dueDate\n      userStory { role goal benefit }\n      commentCount\n      description\n      assignee { id name }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n    }\n  }\n": types.MyTasksDocument,
    "\n  mutation UpdateMyNotifications($emailEnabled: Boolean!) {\n    updateMyNotifications(emailEnabled: $emailEnabled) { id notifyByEmail }\n  }\n": types.UpdateMyNotificationsDocument,
    "\n  mutation UpdateMyAvatar($avatar: Upload!) {\n    updateMyAvatar(avatar: $avatar) { id avatarUrl }\n  }\n": types.UpdateMyAvatarDocument,
    "\n  mutation DeleteUserAvatar($id: ID!) {\n    deleteUserAvatar(id: $id) { id avatarUrl }\n  }\n": types.DeleteUserAvatarDocument,
    "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n": types.ProjectOverviewDocument,
    "\n  query ProjectTimeline($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n          tasks {\n            id\n            title\n            description\n            icon\n            status\n            dueDate\n            userStory { role goal benefit }\n            commentCount\n            labels { id name color position }\n            assignee { id name avatarUrl }\n          }\n        }\n      }\n    }\n  }\n": types.ProjectTimelineDocument,
    "\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      description\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n": types.ProjectTodosDocument,
    "\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n": types.CreateMilestoneDocument,
    "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status } }\n  }\n": types.UpdateMilestoneDocument,
    "\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id }\n  }\n": types.DeleteMilestoneDocument,
    "\n  mutation CreateTodo($input: CreateTodoInput!) {\n    createTodo(input: $input) { id }\n  }\n": types.CreateTodoDocument,
    "\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title description icon dueDate }\n  }\n": types.UpdateTodoDocument,
    "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id }\n  }\n": types.DeleteTodoDocument,
    "\n  query Projects {\n    projects {\n      id\n      name\n      description\n      viewerRole { key names { de en } }\n      progress { done total percent }\n      members {\n        role { key names { de en } }\n        user { id name avatarUrl }\n      }\n    }\n  }\n": types.ProjectsDocument,
    "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n      name\n      description\n      icon\n      viewerRole { key names { de en } }\n      viewerRights\n      labelSet { id name labels { id name category color position } }\n    }\n  }\n": types.ProjectHeaderDocument,
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
    "\n  query ProjectSettings($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      icon\n      locale\n      members {\n        role { key names { de en } }\n        user { id name email avatarUrl }\n      }\n    }\n  }\n": types.ProjectSettingsDocument,
    "\n  query MemberCandidates($projectId: ID) {\n    memberCandidates(projectId: $projectId) { id name avatarUrl isMember }\n  }\n": types.MemberCandidatesDocument,
    "\n  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {\n    updateProject(id: $id, input: $input) { id name description icon locale }\n  }\n": types.UpdateProjectDocument,
    "\n  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {\n    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl } }\n    }\n  }\n": types.SetProjectMemberDocument,
    "\n  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {\n    removeProjectMember(projectId: $projectId, userId: $userId) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl } }\n    }\n  }\n": types.RemoveProjectMemberDocument,
    "\n  mutation DeleteProject($id: ID!) {\n    deleteProject(id: $id) { id }\n  }\n": types.DeleteProjectDocument,
    "\n  query Kanban($projectId: ID!) {\n    kanban(projectId: $projectId) {\n      status\n      tasks {\n        id\n        title\n        icon\n        status\n        boardPosition\n        dueDate\n        commit\n        userStory { role goal benefit }\n        commentCount\n        labels { id name color position }\n        assignee { id name avatarUrl }\n        todo { id title }\n      }\n    }\n  }\n": types.KanbanDocument,
    "\n  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {\n    moveTaskToTodo(id: $id, todoId: $todoId) { id }\n  }\n": types.MoveTaskToTodoDocument,
    "\n  query ProjectMembers($id: ID!) {\n    project(id: $id) {\n      id\n      members {\n        role { key }\n        user { id name avatarUrl }\n      }\n    }\n  }\n": types.ProjectMembersDocument,
    "\n  query TaskDetail($id: ID!) {\n    task(id: $id) {\n      id\n      title\n      description\n      icon\n      status\n      boardPosition\n      dueDate\n      commit\n      userStory { role goal benefit }\n      assignee { id name avatarUrl }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name labelSet { id labels { id name category color position } } }\n        }\n      }\n      comments {\n        id\n        body\n        createdAt\n        updatedAt\n        viewerCanEdit\n        viewerCanDelete\n        author { id name avatarUrl }\n      }\n    }\n  }\n": types.TaskDetailDocument,
    "\n  mutation CreateTask($input: CreateTaskInput!) {\n    createTask(input: $input) { id }\n  }\n": types.CreateTaskDocument,
    "\n  mutation UpdateTask($id: ID!, $input: UpdateTaskInput!) {\n    updateTask(id: $id, input: $input) { id title description icon dueDate userStory { role goal benefit } }\n  }\n": types.UpdateTaskDocument,
    "\n  mutation MoveTask($id: ID!, $status: TaskStatus!, $position: Int!) {\n    moveTask(id: $id, status: $status, position: $position) { id status boardPosition }\n  }\n": types.MoveTaskDocument,
    "\n  mutation AssignTask($id: ID!, $assigneeId: ID) {\n    assignTask(id: $id, assigneeId: $assigneeId) { id assignee { id name avatarUrl } }\n  }\n": types.AssignTaskDocument,
    "\n  mutation DeleteTask($id: ID!) {\n    deleteTask(id: $id) { id }\n  }\n": types.DeleteTaskDocument,
    "\n  mutation AddComment($input: AddCommentInput!) {\n    addComment(input: $input) { id }\n  }\n": types.AddCommentDocument,
    "\n  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {\n    updateComment(id: $id, input: $input) { id body updatedAt }\n  }\n": types.UpdateCommentDocument,
    "\n  mutation DeleteComment($id: ID!) {\n    deleteComment(id: $id) { id }\n  }\n": types.DeleteCommentDocument,
    "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      role { key names { de en } }\n      activity\n      avatarUrl\n      notifyByEmail\n      approved\n      emailVerified\n    }\n    appSettings {\n      twoFactorEnabled\n    }\n    viewerRights\n  }\n": types.ViewerDocument,
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
export function graphql(source: "\n  query AdminUsers {\n    users {\n      id\n      name\n      email\n      role { key isDefault names { de en } }\n      activity\n      avatarUrl\n      twoFactorConfirmed\n      hasPasskey\n      createdAt\n      approved\n      emailVerified\n    }\n  }\n"): (typeof documents)["\n  query AdminUsers {\n    users {\n      id\n      name\n      email\n      role { key isDefault names { de en } }\n      activity\n      avatarUrl\n      twoFactorConfirmed\n      hasPasskey\n      createdAt\n      approved\n      emailVerified\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ApproveUser($id: ID!) {\n    approveUser(id: $id) { id approved }\n  }\n"): (typeof documents)["\n  mutation ApproveUser($id: ID!) {\n    approveUser(id: $id) { id approved }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RejectUser($id: ID!) {\n    rejectUser(id: $id)\n  }\n"): (typeof documents)["\n  mutation RejectUser($id: ID!) {\n    rejectUser(id: $id)\n  }\n"];
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
export function graphql(source: "\n  mutation DraftProjectPlan($messages: [PlanChatMessageInput!]!, $currentPlan: ProjectPlanInput) {\n    draftProjectPlan(messages: $messages, currentPlan: $currentPlan) {\n      reply\n      plan {\n        name\n        description\n        locale\n        members { email role }\n        milestones {\n          title\n          description\n          dueDate\n          todos {\n            title\n            description\n            dueDate\n            tasks { title description dueDate assigneeEmail }\n          }\n        }\n      }\n    }\n  }\n"): (typeof documents)["\n  mutation DraftProjectPlan($messages: [PlanChatMessageInput!]!, $currentPlan: ProjectPlanInput) {\n    draftProjectPlan(messages: $messages, currentPlan: $currentPlan) {\n      reply\n      plan {\n        name\n        description\n        locale\n        members { email role }\n        milestones {\n          title\n          description\n          dueDate\n          todos {\n            title\n            description\n            dueDate\n            tasks { title description dueDate assigneeEmail }\n          }\n        }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation ImportProjectPlan($plan: ProjectPlanInput!, $notifyAssignees: Boolean) {\n    importProjectPlan(plan: $plan, notifyAssignees: $notifyAssignees) {\n      id\n      name\n    }\n  }\n"): (typeof documents)["\n  mutation ImportProjectPlan($plan: ProjectPlanInput!, $notifyAssignees: Boolean) {\n    importProjectPlan(plan: $plan, notifyAssignees: $notifyAssignees) {\n      id\n      name\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query AssistantPeople {\n    users { id name email avatarUrl }\n  }\n"): (typeof documents)["\n  query AssistantPeople {\n    users { id name email avatarUrl }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole { key names { de en } }\n        progress { done total percent }\n      }\n    }\n  }\n"): (typeof documents)["\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole { key names { de en } }\n        progress { done total percent }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query LabelSets {\n    viewerRights\n    labelSets {\n      id\n      name\n      description\n      isBuiltin\n      usedByProjects\n      viewerCanUpdate\n      viewerCanDelete\n      labels { id name category color position }\n    }\n  }\n"): (typeof documents)["\n  query LabelSets {\n    viewerRights\n    labelSets {\n      id\n      name\n      description\n      isBuiltin\n      usedByProjects\n      viewerCanUpdate\n      viewerCanDelete\n      labels { id name category color position }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetProjectLabelSet($projectId: ID!, $labelSetId: ID) {\n    setProjectLabelSet(projectId: $projectId, labelSetId: $labelSetId) {\n      id\n      labelSet { id name labels { id name category color position } }\n    }\n  }\n"): (typeof documents)["\n  mutation SetProjectLabelSet($projectId: ID!, $labelSetId: ID) {\n    setProjectLabelSet(projectId: $projectId, labelSetId: $labelSetId) {\n      id\n      labelSet { id name labels { id name category color position } }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {\n    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {\n      id\n      labels { id name color position }\n    }\n  }\n"): (typeof documents)["\n  mutation SetTaskLabels($taskId: ID!, $labelIds: [ID!]!) {\n    setTaskLabels(taskId: $taskId, labelIds: $labelIds) {\n      id\n      labels { id name color position }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateLabelSet($input: LabelSetInput!) {\n    createLabelSet(input: $input) { id name }\n  }\n"): (typeof documents)["\n  mutation CreateLabelSet($input: LabelSetInput!) {\n    createLabelSet(input: $input) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DuplicateLabelSet($id: ID!) {\n    duplicateLabelSet(id: $id) { id name }\n  }\n"): (typeof documents)["\n  mutation DuplicateLabelSet($id: ID!) {\n    duplicateLabelSet(id: $id) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateLabelSet($id: ID!, $input: LabelSetInput!) {\n    updateLabelSet(id: $id, input: $input) { id name }\n  }\n"): (typeof documents)["\n  mutation UpdateLabelSet($id: ID!, $input: LabelSetInput!) {\n    updateLabelSet(id: $id, input: $input) { id name }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteLabelSet($id: ID!) {\n    deleteLabelSet(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteLabelSet($id: ID!) {\n    deleteLabelSet(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query MyTasks($status: TaskStatus) {\n    myTasks(status: $status) {\n      id\n      title\n      icon\n      status\n      dueDate\n      userStory { role goal benefit }\n      commentCount\n      description\n      assignee { id name }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n    }\n  }\n"): (typeof documents)["\n  query MyTasks($status: TaskStatus) {\n    myTasks(status: $status) {\n      id\n      title\n      icon\n      status\n      dueDate\n      userStory { role goal benefit }\n      commentCount\n      description\n      assignee { id name }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateMyNotifications($emailEnabled: Boolean!) {\n    updateMyNotifications(emailEnabled: $emailEnabled) { id notifyByEmail }\n  }\n"): (typeof documents)["\n  mutation UpdateMyNotifications($emailEnabled: Boolean!) {\n    updateMyNotifications(emailEnabled: $emailEnabled) { id notifyByEmail }\n  }\n"];
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
export function graphql(source: "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectTimeline($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n          tasks {\n            id\n            title\n            description\n            icon\n            status\n            dueDate\n            userStory { role goal benefit }\n            commentCount\n            labels { id name color position }\n            assignee { id name avatarUrl }\n          }\n        }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectTimeline($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        description\n        icon\n        dueDate\n        release { version status }\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          description\n          icon\n          dueDate\n          status\n          progress { done total percent }\n          tasks {\n            id\n            title\n            description\n            icon\n            status\n            dueDate\n            userStory { role goal benefit }\n            commentCount\n            labels { id name color position }\n            assignee { id name avatarUrl }\n          }\n        }\n      }\n    }\n  }\n"];
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
export function graphql(source: "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status } }\n  }\n"): (typeof documents)["\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title description icon dueDate release { version status } }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id }\n  }\n"];
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
export function graphql(source: "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Projects {\n    projects {\n      id\n      name\n      description\n      viewerRole { key names { de en } }\n      progress { done total percent }\n      members {\n        role { key names { de en } }\n        user { id name avatarUrl }\n      }\n    }\n  }\n"): (typeof documents)["\n  query Projects {\n    projects {\n      id\n      name\n      description\n      viewerRole { key names { de en } }\n      progress { done total percent }\n      members {\n        role { key names { de en } }\n        user { id name avatarUrl }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n      name\n      description\n      icon\n      viewerRole { key names { de en } }\n      viewerRights\n      labelSet { id name labels { id name category color position } }\n    }\n  }\n"): (typeof documents)["\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      planerRevision\n      name\n      description\n      icon\n      viewerRole { key names { de en } }\n      viewerRights\n      labelSet { id name labels { id name category color position } }\n    }\n  }\n"];
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
export function graphql(source: "\n  query ProjectSettings($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      icon\n      locale\n      members {\n        role { key names { de en } }\n        user { id name email avatarUrl }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectSettings($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      icon\n      locale\n      members {\n        role { key names { de en } }\n        user { id name email avatarUrl }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query MemberCandidates($projectId: ID) {\n    memberCandidates(projectId: $projectId) { id name avatarUrl isMember }\n  }\n"): (typeof documents)["\n  query MemberCandidates($projectId: ID) {\n    memberCandidates(projectId: $projectId) { id name avatarUrl isMember }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {\n    updateProject(id: $id, input: $input) { id name description icon locale }\n  }\n"): (typeof documents)["\n  mutation UpdateProject($id: ID!, $input: UpdateProjectInput!) {\n    updateProject(id: $id, input: $input) { id name description icon locale }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {\n    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl } }\n    }\n  }\n"): (typeof documents)["\n  mutation SetProjectMember($projectId: ID!, $userId: ID!, $role: String!) {\n    setProjectMember(projectId: $projectId, userId: $userId, role: $role) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl } }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {\n    removeProjectMember(projectId: $projectId, userId: $userId) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl } }\n    }\n  }\n"): (typeof documents)["\n  mutation RemoveProjectMember($projectId: ID!, $userId: ID!) {\n    removeProjectMember(projectId: $projectId, userId: $userId) {\n      id\n      members { role { key names { de en } } user { id name email avatarUrl } }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteProject($id: ID!) {\n    deleteProject(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteProject($id: ID!) {\n    deleteProject(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Kanban($projectId: ID!) {\n    kanban(projectId: $projectId) {\n      status\n      tasks {\n        id\n        title\n        icon\n        status\n        boardPosition\n        dueDate\n        commit\n        userStory { role goal benefit }\n        commentCount\n        labels { id name color position }\n        assignee { id name avatarUrl }\n        todo { id title }\n      }\n    }\n  }\n"): (typeof documents)["\n  query Kanban($projectId: ID!) {\n    kanban(projectId: $projectId) {\n      status\n      tasks {\n        id\n        title\n        icon\n        status\n        boardPosition\n        dueDate\n        commit\n        userStory { role goal benefit }\n        commentCount\n        labels { id name color position }\n        assignee { id name avatarUrl }\n        todo { id title }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {\n    moveTaskToTodo(id: $id, todoId: $todoId) { id }\n  }\n"): (typeof documents)["\n  mutation MoveTaskToTodo($id: ID!, $todoId: ID!) {\n    moveTaskToTodo(id: $id, todoId: $todoId) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectMembers($id: ID!) {\n    project(id: $id) {\n      id\n      members {\n        role { key }\n        user { id name avatarUrl }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectMembers($id: ID!) {\n    project(id: $id) {\n      id\n      members {\n        role { key }\n        user { id name avatarUrl }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query TaskDetail($id: ID!) {\n    task(id: $id) {\n      id\n      title\n      description\n      icon\n      status\n      boardPosition\n      dueDate\n      commit\n      userStory { role goal benefit }\n      assignee { id name avatarUrl }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name labelSet { id labels { id name category color position } } }\n        }\n      }\n      comments {\n        id\n        body\n        createdAt\n        updatedAt\n        viewerCanEdit\n        viewerCanDelete\n        author { id name avatarUrl }\n      }\n    }\n  }\n"): (typeof documents)["\n  query TaskDetail($id: ID!) {\n    task(id: $id) {\n      id\n      title\n      description\n      icon\n      status\n      boardPosition\n      dueDate\n      commit\n      userStory { role goal benefit }\n      assignee { id name avatarUrl }\n      labels { id name color position }\n      todo {\n        id\n        title\n        milestone {\n          id\n          title\n          project { id name labelSet { id labels { id name category color position } } }\n        }\n      }\n      comments {\n        id\n        body\n        createdAt\n        updatedAt\n        viewerCanEdit\n        viewerCanDelete\n        author { id name avatarUrl }\n      }\n    }\n  }\n"];
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
export function graphql(source: "\n  mutation DeleteTask($id: ID!) {\n    deleteTask(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteTask($id: ID!) {\n    deleteTask(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation AddComment($input: AddCommentInput!) {\n    addComment(input: $input) { id }\n  }\n"): (typeof documents)["\n  mutation AddComment($input: AddCommentInput!) {\n    addComment(input: $input) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {\n    updateComment(id: $id, input: $input) { id body updatedAt }\n  }\n"): (typeof documents)["\n  mutation UpdateComment($id: ID!, $input: UpdateCommentInput!) {\n    updateComment(id: $id, input: $input) { id body updatedAt }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteComment($id: ID!) {\n    deleteComment(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteComment($id: ID!) {\n    deleteComment(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      role { key names { de en } }\n      activity\n      avatarUrl\n      notifyByEmail\n      approved\n      emailVerified\n    }\n    appSettings {\n      twoFactorEnabled\n    }\n    viewerRights\n  }\n"): (typeof documents)["\n  query Viewer {\n    me {\n      id\n      name\n      email\n      role { key names { de en } }\n      activity\n      avatarUrl\n      notifyByEmail\n      approved\n      emailVerified\n    }\n    appSettings {\n      twoFactorEnabled\n    }\n    viewerRights\n  }\n"];

export function graphql(source: string) {
  return (documents as any)[source] ?? {};
}

export type DocumentType<TDocumentNode extends DocumentNode<any, any>> = TDocumentNode extends DocumentNode<  infer TType,  any>  ? TType  : never;