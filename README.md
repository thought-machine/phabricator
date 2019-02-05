# phabricator
*ThoughtMachine.* Most of the changes we've
made for ourselves over the years
except for a few we no longer need
or just don't fit in style wise, but
including some that are hard to find
because either people forgot about
them or simply because they haven't
been released yet, a few we really love,
one we think is just ok, some we did
for free, some we did for money, some
for ourselves without permission and
some for friends as swaps but never on
time and always at our office in Old Street.


# About this repository

We probably won't accept any pull requests for new features as this repository
and the associated changes are purely quality-of-life changes for our own
installation. Changes that improve the style or robustness will probably be
accepted though!

We likewise provide no guarantee you'll find the changes useful for your own
Phabricator install. However, we have documented the changes here so you can
make up your own mind.

# Overview of Changes

Changes are marked in the source clearly and a quick search for "TM CHANGES"
should find them.

 * Differential
   * *New* conduit method added - GetRevisionTransactions
     * We use this method to retrieve a list of the users who have approved a
       revision. This is slowly being phased out in favour of closer integration
       with the OWNERS functionality.
 * Maniphest
   * *Change* to TaskListView
     * We render a custom icon based on the Maniphest subtype.
     * We render the task status
 * People
   * *Change* to UserQuery conduit method
     * We always return the user's email as it is not private information in
       our organisation.
 * Project
   * *Change* to ProjectBoardView controller
     * We allow this page to be `frameable` so we can embed it in other dashboards
   * *Change* to ProjectMove controller
     * We disable the "feature" where dragging a task to a column can also change
       its priority as this is clearly nonsense.
   * *Change* to Column storage
     * We reverse the order of milestone columns so the newest is on the left
     * We default to 'Priority' order
   * *Change* to BoardTaskCard view
     * Again we show a custom icon based on the subtype.
     * We show the assigned user's name on the card.

# Installation Instructions

```
git clone https://github.com/thought-machine/phabricator tmphabricator
```

Add `tmphabricator` to your Phabricator `load-libraries`:

```
  "load-libraries": [
    "tmphabricator/src"
  ],
```
