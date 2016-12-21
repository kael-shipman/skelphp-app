# SkelPHP App

*NOTE: The Skel framework is an __experimental__ web applications framework that I've created as an exercise in various systems design concepts. While I do intend to use it regularly on personal projects, it was not necessarily intended to be a "production" framework, since I don't ever plan on providing extensive technical support (though I do plan on providing extensive documentation). It should be considered a thought experiment and it should be used at your own risk. Read more about its conceptual foundations at [my website](https://colors.kaelshipman.me/about/this-website).*

The Skel `App` package is actually a bit of an anticlimax. Most of the work it does is actually provided by other classes. It provides some basic functionality for routing `Requests` to application methods and then turning the returned `Components` into `Responses`, but in this basic form, it's not really rocket science.

I do, however, expect that `App` will eventually be responsible for coordinating several important components. Specifically, it will likely juggle the details of localization, authentication, and authorization, (though the actual mechanisms of these functions will be delegated to other objects). The importance of `App`, then, is in its ability to tightly coordinate several different interchangable components in a way that makes it easy to build high quality web applications. We'll see where this goes later....

## Installation

Eventually, this package is intended to be loaded as a composer package. For now, though, because this is still in very active development, I currently use it via a git submodule:

```bash
cd ~/my-website
git submodule add git@github.com:kael-shipman/skelphp-app.git app/dev-src/skelphp/app
```

This allows me to develop it together with the website I'm building with it. For more on the (somewhat awkward and complex) concept of git submodules, see [this page](https://git-scm.com/book/en/v2/Git-Tools-Submodules).

