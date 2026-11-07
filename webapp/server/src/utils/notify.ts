import { sql } from '../db.js';
import type sqlPkg from 'mssql';

/**
 * In-app notification row only -- same shape as the PHP side's
 * notify_staff() (includes/notification_helper.php), minus the push send
 * (Firebase isn't wired up on this new backend yet). Called after the
 * triggering transaction commits, as its own statement.
 */
export async function notifyStaff(
  pool: sqlPkg.ConnectionPool,
  staffId: number,
  title: string,
  message: string,
  notificationTypeId: number,
  relatedEntityType: string,
  relatedEntityId: number | null,
): Promise<void> {
  await pool
    .request()
    .input('staffId', sql.Int, staffId)
    .input('title', sql.NVarChar, title)
    .input('message', sql.NVarChar, message)
    .input('typeId', sql.Int, notificationTypeId)
    .input('entityType', sql.NVarChar, relatedEntityType)
    .input('entityId', sql.Int, relatedEntityId)
    .query(`
      INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
      VALUES (@staffId, @title, @message, @typeId, @entityType, @entityId, 0, GETDATE())
    `);
}
