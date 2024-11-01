// select the menu item from admin menu.
const mItem = document.getElementById("toplevel_page_wpnakama");

// Centering the custom SVG.
const svgEle = mItem.querySelector(".wp-menu-image img");
svgEle.style.display = "inline-block";
svgEle.style.paddingTop = "6px";

// fetch the update_indicator option from api.
wp.apiRequest({
  path: "/WPNakama/v1/options?option_name=wpnakama_update_indicator",
}).then(async (update_indicator) => {
  if (Number(update_indicator) >= 1) {
    // Select parents.
    const mItemName = mItem.querySelector(
      ".toplevel_page_wpnakama .wp-menu-name"
    );
    const updatePageItem = mItem.querySelector(
      ".wp-submenu-wrap li:nth-child(3) a"
    );
    updatePageItem.style.position = "relative";

    // create an element for indicator.
    const indicatorType = getIndicatorColor(Number(update_indicator));
    const indicatorEle1 = indicatorEleCreator(indicatorType);
    const indicatorEle2 = indicatorEleCreator(indicatorType);

    // appending the indicator to menu item.
    mItemName.append(indicatorEle1);
    updatePageItem.append(indicatorEle2);
  }
});

// Indicator element creator.
function indicatorEleCreator(indicatorColorByType = "#FFC43A") {
  const indicatorEle = document.createElement("span");
  indicatorEle.classList.add("update-plugins");
  indicatorEle.style.position = "absolute";
  indicatorEle.style.top = "50%";
  indicatorEle.style.transform = "translateY(-50%)";
  indicatorEle.style.backgroundColor = indicatorColorByType;
  indicatorEle.style.minWidth = "10px";
  indicatorEle.style.height = "10px";
  if ("ltr" === getLangDirection()) {
    indicatorEle.style.marginLeft = "5px";
  } else {
    indicatorEle.style.marginRight = "5px";
  }
  return indicatorEle;
}

// Get user preffered document laungage direction.
function getLangDirection() {
  return document.getElementsByTagName("html").dir ?? "ltr";
}

// Get the color of the indicator.
function getIndicatorColor(indicatorType = 1) {
  return (
    {
      1: "#FFC233", // for major updates or important.
      2: "#00ACFF", // for new features.
      3: "#F75FDE", // for fix.
    }[indicatorType] ?? "#F75FDE"
  );
}
