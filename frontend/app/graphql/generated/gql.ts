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
    "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole\n        progress { done total percent }\n      }\n    }\n  }\n": typeof types.DashboardDocument,
    "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        icon\n        dueDate\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n": typeof types.ProjectOverviewDocument,
    "\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n": typeof types.ProjectTodosDocument,
    "\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n": typeof types.CreateMilestoneDocument,
    "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title dueDate }\n  }\n": typeof types.UpdateMilestoneDocument,
    "\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id }\n  }\n": typeof types.DeleteMilestoneDocument,
    "\n  mutation CreateTodo($input: CreateTodoInput!) {\n    createTodo(input: $input) { id }\n  }\n": typeof types.CreateTodoDocument,
    "\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title dueDate }\n  }\n": typeof types.UpdateTodoDocument,
    "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id }\n  }\n": typeof types.DeleteTodoDocument,
    "\n  query Projects {\n    projects {\n      id\n      name\n      description\n      viewerRole\n      progress { done total percent }\n      members {\n        role\n        user { id name avatarUrl }\n      }\n    }\n  }\n": typeof types.ProjectsDocument,
    "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      viewerRole\n      viewerAbilities\n    }\n  }\n": typeof types.ProjectHeaderDocument,
    "\n  mutation CreateProject($input: CreateProjectInput!) {\n    createProject(input: $input) {\n      id\n      name\n    }\n  }\n": typeof types.CreateProjectDocument,
    "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      isAdmin\n      avatarUrl\n    }\n    appSettings {\n      twoFactorEnabled\n    }\n  }\n": typeof types.ViewerDocument,
};
const documents: Documents = {
    "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole\n        progress { done total percent }\n      }\n    }\n  }\n": types.DashboardDocument,
    "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        icon\n        dueDate\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n": types.ProjectOverviewDocument,
    "\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n": types.ProjectTodosDocument,
    "\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n": types.CreateMilestoneDocument,
    "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title dueDate }\n  }\n": types.UpdateMilestoneDocument,
    "\n  mutation DeleteMilestone($id: ID!) {\n    deleteMilestone(id: $id) { id }\n  }\n": types.DeleteMilestoneDocument,
    "\n  mutation CreateTodo($input: CreateTodoInput!) {\n    createTodo(input: $input) { id }\n  }\n": types.CreateTodoDocument,
    "\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title dueDate }\n  }\n": types.UpdateTodoDocument,
    "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id }\n  }\n": types.DeleteTodoDocument,
    "\n  query Projects {\n    projects {\n      id\n      name\n      description\n      viewerRole\n      progress { done total percent }\n      members {\n        role\n        user { id name avatarUrl }\n      }\n    }\n  }\n": types.ProjectsDocument,
    "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      viewerRole\n      viewerAbilities\n    }\n  }\n": types.ProjectHeaderDocument,
    "\n  mutation CreateProject($input: CreateProjectInput!) {\n    createProject(input: $input) {\n      id\n      name\n    }\n  }\n": types.CreateProjectDocument,
    "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      isAdmin\n      avatarUrl\n    }\n    appSettings {\n      twoFactorEnabled\n    }\n  }\n": types.ViewerDocument,
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
export function graphql(source: "\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole\n        progress { done total percent }\n      }\n    }\n  }\n"): (typeof documents)["\n  query Dashboard($limit: Int) {\n    dashboard {\n      upcomingTodos(limit: $limit) {\n        id\n        title\n        icon\n        dueDate\n        milestone {\n          id\n          title\n          project { id name }\n        }\n      }\n      myOpenTasks {\n        id\n        title\n        icon\n        status\n        dueDate\n        todo {\n          id\n          title\n          milestone {\n            project { id name }\n          }\n        }\n      }\n      projects {\n        id\n        name\n        viewerRole\n        progress { done total percent }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        icon\n        dueDate\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n"): (typeof documents)["\n  query ProjectOverview($id: ID!) {\n    project(id: $id) {\n      id\n      milestones {\n        id\n        title\n        icon\n        dueDate\n        status\n        progress { done total percent }\n        todos {\n          id\n          title\n          icon\n          dueDate\n          status\n          progress { done total percent }\n        }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n"): (typeof documents)["\n  query ProjectTodos($projectId: ID!, $milestoneId: ID, $status: ItemStatus) {\n    todos(projectId: $projectId, milestoneId: $milestoneId, status: $status) {\n      id\n      title\n      icon\n      dueDate\n      status\n      progress { done total percent }\n      milestone { id title }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n"): (typeof documents)["\n  mutation CreateMilestone($input: CreateMilestoneInput!) {\n    createMilestone(input: $input) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title dueDate }\n  }\n"): (typeof documents)["\n  mutation UpdateMilestone($id: ID!, $input: UpdateMilestoneInput!) {\n    updateMilestone(id: $id, input: $input) { id title dueDate }\n  }\n"];
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
export function graphql(source: "\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title dueDate }\n  }\n"): (typeof documents)["\n  mutation UpdateTodo($id: ID!, $input: UpdateTodoInput!) {\n    updateTodo(id: $id, input: $input) { id title dueDate }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id }\n  }\n"): (typeof documents)["\n  mutation DeleteTodo($id: ID!) {\n    deleteTodo(id: $id) { id }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Projects {\n    projects {\n      id\n      name\n      description\n      viewerRole\n      progress { done total percent }\n      members {\n        role\n        user { id name avatarUrl }\n      }\n    }\n  }\n"): (typeof documents)["\n  query Projects {\n    projects {\n      id\n      name\n      description\n      viewerRole\n      progress { done total percent }\n      members {\n        role\n        user { id name avatarUrl }\n      }\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      viewerRole\n      viewerAbilities\n    }\n  }\n"): (typeof documents)["\n  query ProjectHeader($id: ID!) {\n    project(id: $id) {\n      id\n      name\n      description\n      viewerRole\n      viewerAbilities\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  mutation CreateProject($input: CreateProjectInput!) {\n    createProject(input: $input) {\n      id\n      name\n    }\n  }\n"): (typeof documents)["\n  mutation CreateProject($input: CreateProjectInput!) {\n    createProject(input: $input) {\n      id\n      name\n    }\n  }\n"];
/**
 * The graphql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function graphql(source: "\n  query Viewer {\n    me {\n      id\n      name\n      email\n      isAdmin\n      avatarUrl\n    }\n    appSettings {\n      twoFactorEnabled\n    }\n  }\n"): (typeof documents)["\n  query Viewer {\n    me {\n      id\n      name\n      email\n      isAdmin\n      avatarUrl\n    }\n    appSettings {\n      twoFactorEnabled\n    }\n  }\n"];

export function graphql(source: string) {
  return (documents as any)[source] ?? {};
}

export type DocumentType<TDocumentNode extends DocumentNode<any, any>> = TDocumentNode extends DocumentNode<  infer TType,  any>  ? TType  : never;