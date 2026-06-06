const path = require("path");
const FILENAME = "chocante-vat-eu";

module.exports = {
	mode: "production",
	entry: {
		[FILENAME]: `./js/${FILENAME}.js`,
	},
	output: {
		path: path.resolve(__dirname, "js"),
		filename: "[name].min.js",
	},
};
