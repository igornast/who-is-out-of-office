# who-is-out-of-office
The Online Staff Leave Planner

## 🛠️ Admin Account

The database migrations include a default admin account for initial access:

- **Email:** `admin@ooo.com`
- **Password:** `admin`

> ⚠️ **Important:** This account is intended for development and testing purposes only.  
> Please update the password or remove it after deploying to a production environment.
 

## 📅 Public Holiday Import

You can import a set of public holidays for a specific country and year using the following Symfony command:

```php bin/console app:holiday:import NG Nigeria 2025```

## Slack Integration

This section describes how to integrate Slack notifications into the leave‑planner application.

---

### 1. Configuration

1. Make sure to define these environment variables in your `.env.local` or server environment:

```dotenv
###> symfony/notifier ###
SLACK_DSN=""
SLACK_SIGNING_SECRET=""
# Channel for absence request approval notifications
SLACK_AR_APPROVE_CHANNEL_ID=""
# Channel for the absences daily digest
SLACK_AR_HR_DIGEST_CHANNEL_ID=""
###< symfony/notifier ###
```

---

### 2. Sending Notifications

The app uses two channels to communicate with the company members.
* SLACK_AR_APPROVE_CHANNEL_ID - channel used for in-slack approval actions. Managers can approve or reject requests
  directly from the slack integration bot.
* SLACK_AR_HR_DIGEST_CHANNEL_ID - weekly digest with absences and birthdays information.
* 
---

### 3. Verifying Incoming Requests

The app uses `SLACK_SIGNING_SECRET` to verify if the incoming api messages has been sent by the absence bot app.
For more details on the implementation check `RequestVerifier` class in the slack module.

---

### 4. User‑Specific DMs

Once a user has configured their `slackMemberId`, and enabled the custom app. The bot can send them private updates.

---

### 5. Weekly Digest (Scheduled Task)

You can use weekly digest command to get summaries at a specific point in time. To trigger digestions 
run the `slack:weekly_digest` command, which you can use in cron and set the schedule to every Monday at 8 am.

Example: `0 8 * * MON php bin/console slack:weekly_digest`

The bot will post a summary of:

- Who is out this week.
- Birthdays for this week.
- Fallback message if no absences or birthdays.

---

### 6. Slack Bot Setup & Approval Workflow


1. **Install the Leave Planner Bot**
   - Create a new Slack App and add it to your workspace.
   - Generate an OAuth token and set `SLACK_DSN` to include it.
   - Grant the bot the `chat:write` scope.
   - Learn more about OAuth setup here: https://api.slack.com/authentication/oauth-v2

2. **Enable Interactivity**
   - In your Slack App settings, navigate to **Interactivity & Shortcuts**.
   - Set the **Request URL** to:
     ```
     https://your-domain.com/api/slack/interactive-endpoint
     ```

3. **Post Approval Requests**
   - Whenever someone submits a leave request, the bot will announce it in the approval channel.
   - The message includes **Approve** and **Reject** buttons for managers.

4. **Handle Button Clicks**
   - When a manager clicks **Approve** or **Reject**, Slack sends a `block_actions` payload to the interactive endpoint.
   - Leave Planner app:
      1. Verifies the Slack signature.
      2. Reads the action button `value`.
      3. Updates the leave request status in the system.
      4. Updates the original Slack message to reflect the outcome.

5. **Notify the Requester**  
   If the user has provided a Slack member ID, the bot will send them a direct message with the updated request status.  
