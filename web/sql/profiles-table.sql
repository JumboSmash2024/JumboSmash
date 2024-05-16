-- User profiles, not every user has one set up yet though
CREATE TABLE profiles (
    profile_user INT UNSIGNED NOT NULL,
    profile_text TEXT,
    profile_link TEXT,

    PRIMARY KEY(profile_user)
)
