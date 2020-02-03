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

 * Aphront
   * AphrontResponse is extended to allow TM SecOps to embed `frameable` pages.
 * Auth
   * Modified the Google auth provider to support Cloud IAP.
 * Celerity
   * Fix a typo in CelerityStaticResourceResponse that means `frameable`
     does not work.
 * Externals
   * Added SimpleJWT for use with Cloud IAP auth.
 * People
   * *Change* to UserQuery conduit method
     * We always return the user's email as it is not private information in
       our organisation.
 * Project
   * *Change* to ProjectBoardView controller
     * We allow this page to be `frameable` so we can embed it in other dashboards
   * *Change* to ProjectBoardTaskCard
     * We display the current status on the task.

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
