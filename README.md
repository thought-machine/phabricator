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

 * Aphlict
   * Added a mode to allow it to run in the foreground without debug messages.
 * Auth
   * Modified the Google auth provider to support Cloud IAP.
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

Clone both the Phabricator repository and this one so that the two local copies
are in sibling directories:

```
git clone https://secure.phabricator.com/source/phabricator.git
git clone https://github.com/thought-machine/phabricator tmphabricator

ls -1
# phabricator/
# tmphabricator/
```

In the `.arcconfig` file in the Phabricator repository, add the path to this
repository's `src` directory *relative to the common root directory* to
[`load-libraries`](https://secure.phabricator.com/book/phabcontrib/article/adding_new_classes/#linking-with-phabricator):

```
  "load-libraries": [
    "tmphabricator/src"
  ],
```

# Generating the library map

The modules in this repository need to be referenced in some auto-generated
files. To generate them, ensure you have followed the installation instructions
above, then run `arc liberate` from the root of this repository:

```
cd tmphabricator
arc liberate
# Should output "Done."
```

**NOTE:** `arc liberate` automatically attempts to build `libxhpast` in
`/opt/libphutil/support/xhpast/` if it detects that it doesn't already exist.
This will fail unless `arc liberate` is run as root. It should only be necessary
to do this once, usually when you run `arc liberate` for the first time.

More information can be found [here](https://secure.phabricator.com/book/phabcontrib/article/adding_new_classes/#initializing-a-library)
