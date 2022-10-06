'use strict';

var gulp         = require('gulp');
var sass         = require('gulp-sass')(require('sass'));
var rename       = require('gulp-rename');
var autoprefixer = require('gulp-autoprefixer');

var paths = {
	scss_core : [ 'css/scss/**/*.scss', 'css/' ]
};

/** Core */
gulp.task( 'scss_core', function () {
	return gulp.src(paths.scss_core[0])
		.pipe(sass({ 'outputStyle': 'expanded' }).on('error', sass.logError))
		.pipe( autoprefixer({
			browsers: ['last 2 versions'],
			cascade: false
		}) )
		.pipe(rename('./dolisirh.css'))
		.pipe(gulp.dest(paths.scss_core[1]))
		.pipe(sass({outputStyle: 'compressed'}).on('error', sass.logError))
		.pipe(rename('./dolisirh.min.css'))
		.pipe(gulp.dest(paths.scss_core[1]));
});

/** Watch */
gulp.task( 'default', function () {
	gulp.watch(paths.scss_core[0], gulp.series('scss_core'));
});
