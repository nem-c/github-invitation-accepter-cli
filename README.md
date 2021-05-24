# github-invitation-accepter-cli

Use with caution:

```bash
php github-accept-all-invitations-from-org.php

Paste your github token (should have all privileges assigned):

Org name to accept invitations from []:
```

Both values will be stored.
In same directory where script is located, new files would be created:
* .gh-token containing token you pasted. As long as token is valid, you will not be prompted for it again.
* .last-repo containing last repo ownner (organization) you used last. It will change everytime script is executed.

Only invitations from given organization would be processed.

Due to limitations from github batches of 100 are executed.
