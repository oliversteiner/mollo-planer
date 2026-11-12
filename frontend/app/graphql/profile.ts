import { graphql } from '~/graphql/generated'

export const myTasksQuery = graphql(`
  query MyTasks($status: TaskStatus) {
    myTasks(status: $status) {
      id
      title
      icon
      status
      dueDate
      commentCount
      description
      assignee { id name }
      labels { id name color position }
      todo {
        id
        title
        milestone {
          id
          title
          project { id name }
        }
      }
    }
  }
`)

export const updateMyNotificationsMutation = graphql(`
  mutation UpdateMyNotifications($emailEnabled: Boolean!) {
    updateMyNotifications(emailEnabled: $emailEnabled) { id notifyByEmail }
  }
`)

export const updateMyAvatarMutation = graphql(`
  mutation UpdateMyAvatar($avatar: Upload!) {
    updateMyAvatar(avatar: $avatar) { id avatarUrl }
  }
`)

export const deleteUserAvatarMutation = graphql(`
  mutation DeleteUserAvatar($id: ID!) {
    deleteUserAvatar(id: $id) { id avatarUrl }
  }
`)
